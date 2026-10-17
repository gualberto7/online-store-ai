<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrder
{
    public function __construct(private RecordStockMovement $recordMovement) {}

    public function handle(Order $order, User $user, string $reason): Order
    {
        return DB::transaction(function () use ($order, $user, $reason): Order {
            $branch = Branch::query()->whereKey($order->branch_id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->completed_at !== null) {
                throw ValidationException::withMessages(['order' => 'Un pedido cobrado y entregado requiere una devolución; no puede cancelarse.']);
            }
            if ($order->status === OrderStatus::Cancelled) {
                return $order;
            }
            if ($order->status === OrderStatus::Confirmed) {
                $order->load('items.variant');
                foreach ($order->items as $item) {
                    $movement = $this->recordMovement->handle($branch, $item->variant, $user, 'receipt', $item->quantity, "Cancelación del pedido #{$order->id}");
                    $movement->order_id = $order->id;
                    $movement->save();
                }
            }
            $order->status = OrderStatus::Cancelled;
            $order->cancelled_at = now();
            $order->cancellation_reason = $reason;
            $order->save();

            return $order;
        }, attempts: 3);
    }
}
