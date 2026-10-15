<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmOrder
{
    public function __construct(private RecordStockMovement $recordMovement) {}

    public function handle(Order $order, User $user): Order
    {
        return DB::transaction(function () use ($order, $user): Order {
            $branch = Branch::query()->whereKey($order->branch_id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status === OrderStatus::Confirmed) {
                return $order;
            }
            if ($order->status !== OrderStatus::Draft) {
                throw ValidationException::withMessages(['order' => 'Un pedido cancelado no puede confirmarse.']);
            }
            if (! $branch->is_active) {
                throw ValidationException::withMessages(['order' => 'La sucursal está inactiva.']);
            }
            $order->load('items.variant.product');
            if ($order->items->isEmpty()) {
                throw ValidationException::withMessages(['order' => 'El pedido debe contener al menos una variante.']);
            }
            foreach ($order->items as $item) {
                if (! $item->variant->is_active || ! $item->variant->product->is_active) {
                    throw ValidationException::withMessages(['order' => 'El pedido contiene un producto o variante inactiva.']);
                }
                $movement = $this->recordMovement->handle($branch, $item->variant, $user, 'issue', $item->quantity, "Confirmación del pedido #{$order->id}");
                $movement->order_id = $order->id;
                $movement->save();
            }
            $order->status = OrderStatus::Confirmed;
            $order->confirmed_at = now();
            $order->save();

            return $order;
        }, attempts: 3);
    }
}
