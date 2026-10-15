<?php

namespace App\Http\Controllers;

use App\Actions\CancelOrder;
use App\Models\Order;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class OrderCancellationController extends Controller
{
    public function store(Request $request, Team $currentTeam, Order $order, CancelOrder $cancelOrder): RedirectResponse
    {
        Gate::authorize('manage', $order);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $cancelOrder->handle($order, $request->user(), $data['reason']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pedido cancelado.']);

        return to_route('orders.show', ['current_team' => $currentTeam, 'order' => $order]);
    }
}
