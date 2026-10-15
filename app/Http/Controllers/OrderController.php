<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrder;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [Order::class, $currentTeam]);

        return Inertia::render('orders/index', [
            'orders' => $currentTeam->orders()->with('branch:id,name')->orderByDesc('id')->paginate(24),
            'canManage' => $request->user()->can('create', [Order::class, $currentTeam]),
        ]);
    }

    public function create(Team $currentTeam): Response
    {
        Gate::authorize('create', [Order::class, $currentTeam]);

        return Inertia::render('orders/create', [
            'branches' => $currentTeam->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'variants' => ProductVariant::query()->where('team_id', $currentTeam->id)->where('is_active', true)
                ->whereHas('product', fn ($query) => $query->where('is_active', true))
                ->with(['product:id,name', 'stocks:id,branch_id,product_variant_id,quantity'])
                ->orderBy('sku')->get(['id', 'product_id', 'name', 'sku', 'price']),
        ]);
    }

    public function store(StoreOrderRequest $request, Team $currentTeam, CreateOrder $createOrder): RedirectResponse
    {
        $order = $createOrder->handle($currentTeam, $request->user(), $request->orderData());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pedido creado como borrador.']);

        return to_route('orders.show', ['current_team' => $currentTeam, 'order' => $order]);
    }

    public function show(Request $request, Team $currentTeam, Order $order): Response
    {
        Gate::authorize('view', $order);
        $order->load(['branch:id,name', 'items', 'user:id,name']);

        return Inertia::render('orders/show', [
            'order' => $order,
            'canManage' => $request->user()->can('manage', $order),
        ]);
    }
}
