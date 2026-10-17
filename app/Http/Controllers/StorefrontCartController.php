<?php

namespace App\Http\Controllers;

use App\Actions\StorefrontCart;
use App\Http\Requests\UpdateStorefrontCartRequest;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontCartController extends Controller
{
    public function index(StorefrontCart $cart): Response
    {
        return Inertia::render('storefront/cart', ['storefront' => ['name' => config('storefront.name')], 'cart' => $cart->summary()]);
    }

    public function destroy(Request $request, StorefrontCart $cart): RedirectResponse
    {
        $request->session()->forget($cart->key().'.items');

        return to_route('storefront.cart.index');
    }

    public function update(UpdateStorefrontCartRequest $request, StorefrontCart $cart): RedirectResponse
    {
        $id = $request->integer('variant_id');
        $quantity = $request->integer('quantity');
        if ($quantity > 0) {
            ProductVariant::query()->where('team_id', $cart->team()->id ?? 0)->whereKey($id)->where('is_active', true)
                ->whereHas('product', fn ($query) => $query->publiclyVisible())->firstOrFail();
            if (count($cart->quantities()) >= 100 && ! isset($cart->quantities()[$id])) {
                throw ValidationException::withMessages(['quantity' => 'El carrito admite hasta 100 variantes.']);
            }
        }
        $cart->set($id, $quantity);

        return to_route('storefront.cart.index');
    }
}
