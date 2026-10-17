<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_images_are_stored_with_generated_names_and_attached_to_the_product(): void
    {
        Storage::fake('public');
        [$user, $product] = $this->product();

        $this->actingAs($user)->post(route('products.images.store', [$product->team, $product]), [
            'image' => UploadedFile::fake()->image('photo.jpg'), 'product_id' => 999,
        ])->assertRedirect(route('products.show', [$product->team, $product]));

        $image = $product->images()->sole();
        Storage::disk('public')->assertExists($image->path);
        $this->assertStringStartsWith("teams/{$product->team_id}/products/{$product->id}/", $image->path);
        $this->assertStringNotContainsString('photo.jpg', $image->path);
        $this->assertSame(Storage::disk('public')->url($image->path), $image->url);
    }

    public function test_images_can_be_deleted_from_both_the_gallery_and_storage(): void
    {
        Storage::fake('public');
        [$user, $product] = $this->product();
        $path = UploadedFile::fake()->image('photo.png')->store('test', 'public');
        $image = $product->images()->create(['path' => $path]);

        $this->actingAs($user)->delete(route('products.images.destroy', [$product->team, $product, $image]))->assertRedirect();

        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_members_can_view_images_but_cannot_upload_or_delete_them(): void
    {
        Storage::fake('public');
        [$user, $product] = $this->product(TeamRole::Member);
        $path = UploadedFile::fake()->image('photo.jpg')->store('test', 'public');
        $image = $product->images()->create(['path' => $path]);

        $this->actingAs($user)->get(route('products.show', [$product->team, $product]))
            ->assertInertia(fn (Assert $page) => $page->has('images', 1)->where('images.0.url', $image->url));
        $this->post(route('products.images.store', [$product->team, $product]), ['image' => UploadedFile::fake()->image('new.jpg')])->assertForbidden();
        $this->delete(route('products.images.destroy', [$product->team, $product, $image]))->assertForbidden();

        $this->assertModelExists($image);
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseCount('product_images', 1);
    }

    public function test_images_cannot_be_deleted_through_a_different_product(): void
    {
        Storage::fake('public');
        [$user, $product] = $this->product();
        $otherProduct = Product::factory()->for($product->team)->create();
        $path = UploadedFile::fake()->image('photo.jpg')->store('test', 'public');
        $image = $otherProduct->images()->create(['path' => $path]);

        $this->actingAs($user)->delete(route('products.images.destroy', [$product->team, $product, $image]))->assertNotFound();

        $this->assertModelExists($image);
        Storage::disk('public')->assertExists($path);
    }

    public function test_uploading_to_another_business_product_is_not_found(): void
    {
        Storage::fake('public');
        [$user, $product] = $this->product();
        $otherProduct = Product::factory()->create();

        $this->actingAs($user)->post(route('products.images.store', [$product->team, $otherProduct]), [
            'image' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertNotFound();

        $this->assertDatabaseCount('product_images', 0);
        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_the_gallery_limit_does_not_leave_an_unreferenced_upload(): void
    {
        Storage::fake('public');
        [$user, $product] = $this->product();
        for ($index = 0; $index < 10; $index++) {
            $product->images()->create(['path' => "existing-$index.jpg"]);
        }

        $this->actingAs($user)->post(route('products.images.store', [$product->team, $product]), [
            'image' => UploadedFile::fake()->image('extra.jpg'),
        ])->assertSessionHasErrors(['image' => 'Cada producto admite hasta 10 imágenes.']);

        $this->assertDatabaseCount('product_images', 10);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    #[TestWith(['photo.jpg', 'image/jpeg'])]
    #[TestWith(['photo.svg', 'image/svg+xml'])]
    #[TestWith(['document.pdf', 'application/pdf'])]
    public function test_invalid_file_contents_and_svg_are_rejected(string $name, string $mime): void
    {
        Storage::fake('public');
        [$user, $product] = $this->product();

        $this->actingAs($user)->post(route('products.images.store', [$product->team, $product]), [
            'image' => UploadedFile::fake()->create($name, 10, $mime),
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseCount('product_images', 0);
        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_oversized_images_and_excessive_dimensions_are_rejected(): void
    {
        Storage::fake('public');
        [$user, $product] = $this->product();

        $this->actingAs($user)->post(route('products.images.store', [$product->team, $product]), [
            'image' => UploadedFile::fake()->image('large.jpg')->size(1025),
        ])->assertSessionHasErrors('image');
        $this->post(route('products.images.store', [$product->team, $product]), [
            'image' => UploadedFile::fake()->image('wide.jpg', 4097, 10),
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_a_product_without_images_can_be_rendered_and_the_cover_is_the_first_remaining_image(): void
    {
        [$user, $product] = $this->product();
        $this->actingAs($user)->get(route('products.show', [$product->team, $product]))
            ->assertInertia(fn (Assert $page) => $page->has('images', 0));
        $first = $product->images()->create(['path' => 'first.jpg']);
        $second = $product->images()->create(['path' => 'second.jpg']);
        $first->delete();

        $this->get(route('products.index', $product->team))
            ->assertInertia(fn (Assert $page) => $page->has('products.data.0.images', 1)->where('products.data.0.images.0.id', $second->id));
    }

    public function test_async_uploads_return_the_new_gallery_image_without_a_redirect(): void
    {
        Storage::fake('public');
        [$user, $product] = $this->product();

        $response = $this->actingAs($user)->postJson(route('products.images.store', [$product->team, $product]), [
            'image' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $image = $product->images()->sole();
        $response->assertCreated()->assertExactJson(['image' => ['id' => $image->id, 'url' => $image->url]]);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_an_invalid_file_does_not_prevent_the_next_async_upload(): void
    {
        Storage::fake('public');
        [$user, $product] = $this->product();
        $url = route('products.images.store', [$product->team, $product]);

        $this->actingAs($user)->postJson($url, ['image' => UploadedFile::fake()->image('wide.jpg', 4097, 10)])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->postJson($url, ['image' => UploadedFile::fake()->image('valid.png')])->assertCreated();

        $this->assertDatabaseCount('product_images', 1);
        Storage::disk('public')->assertExists($product->images()->sole()->path);
    }

    public function test_async_uploads_enforce_the_gallery_limit_and_clean_up_rejected_files(): void
    {
        Storage::fake('public');
        [$user, $product] = $this->product();
        for ($index = 0; $index < 9; $index++) {
            $product->images()->create(['path' => "existing-$index.jpg"]);
        }
        $url = route('products.images.store', [$product->team, $product]);

        $this->actingAs($user)->postJson($url, ['image' => UploadedFile::fake()->image('tenth.jpg')])->assertCreated();
        $this->postJson($url, ['image' => UploadedFile::fake()->image('eleventh.jpg')])
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->assertDatabaseCount('product_images', 10);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    /** @return array{User, Product} */
    private function product(TeamRole $role = TeamRole::Admin): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $product->team->members()->attach($user, ['role' => $role->value]);

        return [$user, $product];
    }
}
