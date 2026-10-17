<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnOrderItem
{
    public function __construct(private RecordStockMovement $recordMovement) {}

    /** @param array{order_item_id: int, quantity: int, restock: bool, reason: string, payment_method: PaymentMethod, payment_reference: string|null, request_token: string} $data */
    public function handle(Order $order, User $user, array $data): OrderReturn
    {
        return DB::transaction(function () use ($order, $user, $data): OrderReturn {
            $branch = Branch::query()->whereKey($order->branch_id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $existing = $order->returns()->where('request_token', $data['request_token'])->first();
            if ($existing !== null) {
                return $existing;
            }
            if ($order->completed_at === null || $order->status !== OrderStatus::Confirmed) {
                throw ValidationException::withMessages(['order' => 'Solo se pueden devolver pedidos cobrados y entregados.']);
            }
            $item = $order->items()->whereKey($data['order_item_id'])->with('variant')->firstOrFail();
            $returned = (int) $order->returns()->where('order_item_id', $item->id)->sum('quantity');
            if ($data['quantity'] < 1 || $data['quantity'] > $item->quantity - $returned) {
                throw ValidationException::withMessages(['quantity' => 'La cantidad supera las unidades pendientes de devolución.']);
            }
            $return = new OrderReturn([
                'quantity' => $data['quantity'], 'restock' => $data['restock'], 'amount_cents' => $data['quantity'] * $item->unit_price_cents,
                'reason' => $data['reason'], 'payment_method' => $data['payment_method'], 'payment_reference' => $data['payment_reference'], 'request_token' => $data['request_token'],
            ]);
            $return->order_item_id = $item->id;
            $return->user_id = $user->id;
            $order->returns()->save($return);
            if ($data['restock']) {
                $movement = $this->recordMovement->handle($branch, $item->variant, $user, 'receipt', $data['quantity'], "Devolución #{$return->id} del pedido #{$order->id}");
                $movement->order_id = $order->id;
                $movement->save();
                $return->stock_movement_id = $movement->id;
                $return->save();
            }

            return $return;
        }, attempts: 3);
    }
}
