<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteOrder
{
    public function handle(Order $order, User $user, PaymentMethod $method, ?string $reference): Order
    {
        return DB::transaction(function () use ($order, $user, $method, $reference): Order {
            Branch::query()->whereKey($order->branch_id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->completed_at !== null) {
                return $order;
            }
            if ($order->status !== OrderStatus::Confirmed) {
                throw ValidationException::withMessages(['order' => 'Solo se pueden cobrar y entregar pedidos confirmados.']);
            }
            $order->completed_at = now();
            $order->completed_by_user_id = $user->id;
            $order->payment_method = $method;
            $order->payment_reference = $reference;
            $order->save();

            return $order;
        }, attempts: 3);
    }
}
