<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProductRequest;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [Product::class, $currentTeam]);

        return Inertia::render('products/index', [
            'products' => $currentTeam->products()->select(['id', 'name', 'is_active'])
                ->withCount('variants')->orderBy('name')->orderBy('id')->paginate(24),
            'canManage' => $request->user()->can('create', [Product::class, $currentTeam]),
        ]);
    }

    public function create(Team $currentTeam): Response
    {
        Gate::authorize('create', [Product::class, $currentTeam]);

        return Inertia::render('products/form');
    }

    public function store(SaveProductRequest $request, Team $currentTeam): RedirectResponse
    {
        $product = $currentTeam->products()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto creado. Agrega su primera variante.']);

        return to_route('products.show', ['current_team' => $currentTeam, 'product' => $product]);
    }

    public function show(Request $request, Team $currentTeam, Product $product): Response
    {
        Gate::authorize('view', $product);

        return Inertia::render('products/show', [
            'product' => $product->only(['id', 'name', 'description', 'is_active']),
            'variants' => $product->variants()->orderBy('name')->orderBy('id')
                ->get(['id', 'name', 'sku', 'price', 'is_active']),
            'canManage' => $request->user()->can('update', $product),
        ]);
    }

    public function edit(Team $currentTeam, Product $product): Response
    {
        Gate::authorize('update', $product);

        return Inertia::render('products/form', [
            'product' => $product->only(['id', 'name', 'description', 'is_active']),
        ]);
    }

    public function update(SaveProductRequest $request, Team $currentTeam, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto actualizado.']);

        return to_route('products.show', ['current_team' => $currentTeam, 'product' => $product]);
    }
}
