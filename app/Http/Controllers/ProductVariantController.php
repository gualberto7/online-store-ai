<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProductVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductVariantController extends Controller
{
    public function create(Team $currentTeam, Product $product): Response
    {
        Gate::authorize('update', $product);

        return Inertia::render('products/variant-form', [
            'product' => $product->only(['id', 'name']),
        ]);
    }

    public function store(SaveProductVariantRequest $request, Team $currentTeam, Product $product): RedirectResponse
    {
        $variant = new ProductVariant($request->validated());
        $variant->team_id = $currentTeam->id;
        $product->variants()->save($variant);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Variante creada.']);

        return to_route('products.show', ['current_team' => $currentTeam, 'product' => $product]);
    }

    public function edit(Team $currentTeam, Product $product, ProductVariant $variant): Response
    {
        Gate::authorize('update', $product);

        return Inertia::render('products/variant-form', [
            'product' => $product->only(['id', 'name']),
            'variant' => $variant->only(['id', 'name', 'sku', 'price', 'is_active']),
        ]);
    }

    public function update(SaveProductVariantRequest $request, Team $currentTeam, Product $product, ProductVariant $variant): RedirectResponse
    {
        $variant->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Variante actualizada.']);

        return to_route('products.show', ['current_team' => $currentTeam, 'product' => $product]);
    }
}
