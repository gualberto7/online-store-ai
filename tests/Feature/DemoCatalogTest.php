<?php

namespace Tests\Feature;

use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\Team;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DemoGiftProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_one_hundred_categorized_products_and_size_variants_without_network_calls(): void
    {
        $this->seed(AdminUserSeeder::class);
        Http::preventStrayRequests();

        $this->seed(DemoGiftProductSeeder::class);

        $team = Team::query()->where('slug', 'tienda-demo')->sole();
        $this->assertSame(100, $team->products()->count());
        $this->assertSame(100, $team->products()->whereNotNull('category_id')->publiclyVisible()->count());
        $this->assertSame(150, ProductVariant::query()->where('team_id', $team->id)->count());
        $this->assertDatabaseHas('product_variants', ['sku' => 'DEMO-DJ-0083-M', 'name' => 'Talla M']);
        $this->assertDatabaseHas('categories', ['team_id' => $team->id, 'name' => 'Joyería y bisutería']);
        $this->assertDatabaseCount('stocks', 0);
        Http::assertNothingSent();
    }

    public function test_repeating_import_preserves_renamed_products_prices_inactive_categories_images_and_stock(): void
    {
        $this->seed([AdminUserSeeder::class, DemoGiftProductSeeder::class]);
        $variant = ProductVariant::query()->where('sku', 'DEMO-DJ-0083-M')->sole();
        $product = $variant->product;
        $product->update(['name' => 'Mi camisa editada', 'description' => 'Descripción propia', 'is_active' => false]);
        $product->category->update(['is_active' => false]);
        $variant->update(['price' => '45.67', 'is_active' => false]);
        $product->images()->create(['path' => 'custom-photo.jpg']);
        $stock = Stock::factory()->create(['product_variant_id' => $variant->id, 'quantity' => 8]);

        $this->seed(DemoGiftProductSeeder::class);

        $this->assertDatabaseCount('products', 100);
        $this->assertDatabaseCount('product_variants', 150);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Mi camisa editada', 'description' => 'Descripción propia', 'is_active' => false]);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'price' => '45.67', 'is_active' => false]);
        $this->assertDatabaseHas('categories', ['id' => $product->category_id, 'is_active' => false]);
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id, 'path' => 'custom-photo.jpg']);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 8]);
    }

    public function test_image_import_downloads_once_and_stores_real_files_in_the_public_disk(): void
    {
        $this->seed(AdminUserSeeder::class);
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake(['https://cdn.dummyjson.com/*' => Http::response(UploadedFile::fake()->image('photo.png')->getContent())]);

        $this->artisan('catalog:import-demo --images')->assertSuccessful();
        $this->artisan('catalog:import-demo --images')->assertSuccessful();

        $this->assertDatabaseCount('product_images', 100);
        $image = ProductImage::query()->firstOrFail();
        Storage::disk('public')->assertExists($image->path);
        $this->assertStringEndsWith('.png', $image->path);
        Http::assertSentCount(100);
    }

    public function test_invalid_image_responses_do_not_create_broken_images_and_can_be_retried(): void
    {
        $this->seed(AdminUserSeeder::class);
        Storage::fake('public');
        $url = (new DemoGiftProductSeeder)->catalog()[0]['image_url'];
        $photo = UploadedFile::fake()->image('photo.png')->getContent();
        Http::preventStrayRequests();
        Http::fake([$url => Http::sequence()->push('<html>not an image</html>')->push($photo), 'https://cdn.dummyjson.com/*' => Http::response($photo)]);

        $this->artisan('catalog:import-demo --images')->assertFailed();

        $this->assertDatabaseCount('products', 100);
        $this->assertDatabaseCount('product_images', 99);
        $this->artisan('catalog:import-demo --images')->assertSuccessful();
        $this->assertDatabaseCount('product_images', 100);
    }

    public function test_image_download_connection_errors_leave_products_available_for_a_retry(): void
    {
        $this->seed(AdminUserSeeder::class);
        Storage::fake('public');
        $url = (new DemoGiftProductSeeder)->catalog()[0]['image_url'];
        Http::preventStrayRequests();
        Http::fake([$url => Http::failedConnection(), 'https://cdn.dummyjson.com/*' => Http::response(UploadedFile::fake()->image('photo.png')->getContent())]);

        $this->artisan('catalog:import-demo --images')->assertFailed();

        $this->assertDatabaseCount('products', 100);
        $this->assertDatabaseCount('product_images', 99);
    }

    public function test_existing_full_galleries_are_preserved(): void
    {
        $this->seed([AdminUserSeeder::class, DemoGiftProductSeeder::class]);
        Storage::fake('public');
        $variant = ProductVariant::query()->where('sku', 'DEMO-DJ-0001-UNI')->sole();
        for ($index = 0; $index < 10; $index++) {
            $variant->product->images()->create(['path' => "manual-{$index}.jpg"]);
        }
        Http::preventStrayRequests();
        Http::fake(['https://cdn.dummyjson.com/*' => Http::response(UploadedFile::fake()->image('photo.png')->getContent())]);

        $this->artisan('catalog:import-demo --images')->assertSuccessful();

        $this->assertSame(10, $variant->product->images()->count());
        Http::assertSentCount(99);
    }

    public function test_import_requires_the_demo_company_and_does_not_touch_another_business(): void
    {
        $other = Team::factory()->create();

        $this->artisan('catalog:import-demo')->assertFailed();

        $this->assertSame(0, $other->products()->count());
    }
}
