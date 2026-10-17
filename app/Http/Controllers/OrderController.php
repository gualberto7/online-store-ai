<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrder;
use App\Enums\OrderStatus;
use App\Http\Requests\BrowseOrdersRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(BrowseOrdersRequest $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [Order::class, $currentTeam]);

        $search = $request->string('q')->trim()->toString();
        $branchId = $request->integer('branch');
        $status = $request->string('status')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $orderNumber = filter_var(ltrim($search, '#'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $orders = $currentTeam->orders()->with('branch:id,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('customer_name', "%{$search}%")->orWhereLike('customer_phone', "%{$search}%")
                ->when($orderNumber !== false, fn ($query) => $query->orWhere('id', $orderNumber))))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($status === 'pending', fn ($query) => $query->where('status', OrderStatus::Confirmed)->whereNull('completed_at'))
            ->when($status === 'completed', fn ($query) => $query->where('status', OrderStatus::Confirmed)->whereNotNull('completed_at'))
            ->when(in_array($status, ['draft', 'cancelled'], true), fn ($query) => $query->where('status', $status))
            ->when($from !== '', fn ($query) => $query->where('created_at', '>=', $from.' 00:00:00'))
            ->when($to !== '', fn ($query) => $query->where('created_at', '<', CarbonImmutable::parse($to)->addDay()->startOfDay()))
            ->orderByDesc('id')->paginate(24)->withQueryString();

        return Inertia::render('orders/index', [
            'orders' => $orders,
            'branches' => $currentTeam->branches()->orderBy('name')->get(['id', 'name', 'is_active']),
            'filters' => ['q' => $search, 'branch' => $branchId ?: '', 'status' => $status, 'from' => $from, 'to' => $to],
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
        $order->load(['branch:id,name', 'items', 'user:id,name', 'completedBy:id,name', 'returns.user:id,name']);

        return Inertia::render('orders/show', [
            'order' => $order,
            'canManage' => $request->user()->can('manage', $order),
        ]);
    }
}
