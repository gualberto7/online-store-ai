<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmOrder;
use App\Models\Order;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class OrderConfirmationController extends Controller
{
    public function store(Request $request, Team $currentTeam, Order $order, ConfirmOrder $confirmOrder): RedirectResponse
    {
        Gate::authorize('manage', $order);
        $confirmOrder->handle($order, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pedido confirmado.']);

        return to_route('orders.show', ['current_team' => $currentTeam, 'order' => $order]);
    }
}
