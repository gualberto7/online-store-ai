<?php

namespace App\Providers;

use App\Actions\InvalidateProductEmbedding;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Product::saved(function (Product $product): void {
            if ($product->wasChanged(['name', 'description', 'category_id', 'is_active']) || $product->wasRecentlyCreated) {
                app(InvalidateProductEmbedding::class)->handle($product->id);
            }
        });
        ProductVariant::saved(function (ProductVariant $variant): void {
            if ($variant->wasChanged(['name', 'sku', 'is_active', 'product_id']) || $variant->wasRecentlyCreated) {
                app(InvalidateProductEmbedding::class)->handle($variant->product_id);
            }
        });
        ProductVariant::deleted(fn (ProductVariant $variant) => app(InvalidateProductEmbedding::class)->handle($variant->product_id));
        Category::saved(function (Category $category): void {
            if ($category->wasChanged(['name', 'is_active'])) {
                $category->products()->select('id')->chunkById(50, function ($products): void {
                    foreach ($products as $product) {
                        app(InvalidateProductEmbedding::class)->handle($product->id);
                    }
                });
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
