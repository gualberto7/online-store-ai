<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrowseStorefrontRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\Team;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function index(BrowseStorefrontRequest $request): Response
    {
        $team = $this->storeTeam();
        $search = $request->string('q')->trim()->toString();
        $categoryId = $request->integer('category');
        $branchId = $request->integer('branch');
        $sort = $request->input('sort') ?: 'name';
        $availableVariants = fn ($query) => $query->where('is_active', true)
            ->whereHas('stocks', fn ($query) => $query->where('quantity', '>', 0)
                ->whereHas('branch', fn ($query) => $query->where('is_active', true))
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId)));

        $products = Product::query()->select(['id', 'name', 'category_id'])->where('team_id', $team->id ?? 0)->publiclyVisible()
            ->with(['category:id,name', 'images' => fn ($query) => $query->limit(1)])
            ->withMin(['variants as starting_price' => fn ($query) => $query->where('is_active', true)], 'price')
            ->withExists(['variants as available' => $availableVariants])
            ->withCasts(['starting_price' => 'decimal:2', 'available' => 'boolean'])
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', "%{$search}%")->orWhereLike('description', "%{$search}%")
                ->orWhereHas('variants', fn ($query) => $query->where('is_active', true)->whereLike('sku', "%{$search}%"))))
            ->when($request->boolean('available'), fn ($query) => $query->whereHas('variants', $availableVariants))
            ->orderBy($sort === 'name' ? 'name' : 'starting_price', $sort === 'price_desc' ? 'desc' : 'asc')->orderBy('id')
            ->paginate(12)->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id, 'name' => $product->name,
                'category' => $product->category?->name,
                'image' => $product->images->first()?->url,
                'price_from' => $product->getAttribute('starting_price'),
                'available' => $product->getAttribute('available'),
            ]);

        return Inertia::render('storefront/index', [
            'storefront' => ['name' => config('storefront.name')],
            'products' => $products,
            'categories' => $team?->categories()->where('is_active', true)->orderBy('name')->get(['id', 'name']) ?? [],
            'branches' => $team?->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'address']) ?? [],
            'filters' => ['q' => $search, 'category' => $categoryId ?: '', 'branch' => $branchId ?: '', 'available' => $request->boolean('available'), 'sort' => $sort],
        ]);
    }

    public function show(Product $product): Response
    {
        $team = $this->storeTeam();
        abort_unless($team !== null, 404);
        $product = Product::query()->where('team_id', $team->id)->publiclyVisible()->whereKey($product->id)->firstOrFail();
        $product->load(['category:id,name', 'images', 'variants' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
            'variants.stocks' => fn ($query) => $query->where('quantity', '>', 0)->whereHas('branch', fn ($query) => $query->where('is_active', true)),
        ]);

        return Inertia::render('storefront/show', [
            'storefront' => ['name' => config('storefront.name')],
            'product' => [
                'id' => $product->id, 'name' => $product->name, 'description' => $product->description,
                'category' => $product->category?->name,
                'images' => $product->images->map(fn (ProductImage $image) => $image->only(['id', 'url'])),
                'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id, 'name' => $variant->name, 'sku' => $variant->sku, 'price' => $variant->price,
                    'available_at' => $variant->stocks->map(fn (Stock $stock) => $stock->branch_id)->values(),
                ]),
            ],
            'branches' => $team->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'address']),
        ]);
    }

    private function storeTeam(): ?Team
    {
        $teamId = filter_var(config('storefront.team_id'), FILTER_VALIDATE_INT);
        if ($teamId === false) {
            return null;
        }

        return Team::query()->whereKey($teamId)->where('is_personal', false)->first();
    }
}
