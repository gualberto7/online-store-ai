<?php

namespace App\Http\Controllers;

use App\Actions\PlaceStorefrontOrder;
use App\Actions\StorefrontCart;
use App\Http\Requests\StorefrontCheckoutRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontCheckoutController extends Controller
{
    public function create(Request $request, StorefrontCart $cart): Response|RedirectResponse
    {
        if ($cart->quantities() === []) {
            return to_route('storefront.cart.index');
        }
        $team = $cart->team();
        abort_unless($team !== null, 404);
        $token = (string) Str::uuid();
        $tokens = $request->session()->get('storefront_checkout_tokens', []);
        $tokens[] = $token;
        $request->session()->put('storefront_checkout_tokens', array_slice($tokens, -20));

        return Inertia::render('storefront/checkout', ['storefront' => ['name' => config('storefront.name')], 'cart' => $cart->summary(), 'token' => $token,
            'branches' => $team->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'address'])]);
    }

    public function store(StorefrontCheckoutRequest $request, StorefrontCart $cart, PlaceStorefrontOrder $placeOrder): RedirectResponse
    {
        $isRetry = Order::query()->where('checkout_token', $request->string('token')->toString())->exists();
        $order = $placeOrder->handle($cart, ['branch_id' => $request->integer('branch_id'), 'customer_name' => $request->string('customer_name')->toString(),
            'customer_email' => $request->string('customer_email')->toString(), 'customer_phone' => $request->string('customer_phone')->toString(),
            'notes' => $request->filled('notes') ? $request->string('notes')->toString() : null, 'token' => $request->string('token')->toString()]);
        if (! $isRetry) {
            $request->session()->forget($cart->key().'.items');
        }

        return to_route('storefront.checkout.show', ['token' => $order->checkout_token]);
    }

    public function show(Request $request, string $token, StorefrontCart $cart): Response
    {
        abort_unless(in_array($token, $request->session()->get('storefront_checkout_tokens', []), true), 404);
        $order = Order::query()->where('team_id', $cart->team()->id ?? 0)->where('checkout_token', $token)->with(['branch', 'items'])->firstOrFail();

        return Inertia::render('storefront/receipt', ['storefront' => ['name' => config('storefront.name')], 'order' => [
            'completed_at' => $order->completed_at?->toIso8601String(),
            'refunded_cents' => (int) $order->returns()->sum('amount_cents'),
            'id' => $order->id, 'status' => $order->status->value, 'customer_name' => $order->customer_name,
            'total_cents' => $order->total_cents, 'branch' => $order->branch->only(['name', 'address']),
            'items' => $order->items->map(fn ($item) => $item->only(['id', 'product_name', 'variant_name', 'quantity', 'unit_price_cents']))]]);
    }
}
