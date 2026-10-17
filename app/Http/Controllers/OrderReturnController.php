<?php

namespace App\Http\Controllers;

use App\Actions\ReturnOrderItem;
use App\Enums\PaymentMethod;
use App\Http\Requests\StoreOrderReturnRequest;
use App\Models\Order;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrderReturnController extends Controller
{
    public function store(StoreOrderReturnRequest $request, Team $currentTeam, Order $order, ReturnOrderItem $returnItem): RedirectResponse
    {
        $returnItem->handle($order, $request->user(), ['order_item_id' => $request->integer('order_item_id'), 'quantity' => $request->integer('quantity'),
            'restock' => $request->boolean('restock'), 'reason' => $request->string('reason')->toString(),
            'payment_method' => PaymentMethod::from($request->string('payment_method')->toString()),
            'payment_reference' => $request->filled('payment_reference') ? $request->string('payment_reference')->toString() : null,
            'request_token' => $request->string('request_token')->toString()]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Devolución y reintegro registrados.']);

        return to_route('orders.show', ['current_team' => $currentTeam, 'order' => $order]);
    }
}
