<?php

namespace App\Http\Controllers;

use App\Actions\CompleteOrder;
use App\Enums\PaymentMethod;
use App\Http\Requests\CompleteOrderRequest;
use App\Models\Order;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrderCompletionController extends Controller
{
    public function store(CompleteOrderRequest $request, Team $currentTeam, Order $order, CompleteOrder $completeOrder): RedirectResponse
    {
        $completeOrder->handle($order, $request->user(), PaymentMethod::from($request->string('payment_method')->toString()),
            $request->filled('payment_reference') ? $request->string('payment_reference')->toString() : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cobro y entrega registrados.']);

        return to_route('orders.show', ['current_team' => $currentTeam, 'order' => $order]);
    }
}
