<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrder
{
    /** @param array{branch_id: int, customer_name?: string|null, notes?: string|null, items: array<int, array{variant_id: int, quantity: int}>} $data */
    public function handle(Team $team, User $user, array $data): Order
    {
        return DB::transaction(function () use ($team, $user, $data): Order {
            $branch = Branch::query()->where('team_id', $team->id)->whereKey($data['branch_id'])->lockForUpdate()->firstOrFail();
            if (! $branch->is_active) {
                throw ValidationException::withMessages(['branch_id' => 'La sucursal está inactiva.']);
            }
            $variants = ProductVariant::query()->where('team_id', $team->id)
                ->whereIn('id', array_column($data['items'], 'variant_id'))->with('product')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $order = new Order(['customer_name' => $data['customer_name'] ?? null, 'notes' => $data['notes'] ?? null]);
            $order->branch_id = $branch->id;
            $order->user_id = $user->id;
            $order->status = OrderStatus::Draft;
            $order->total_cents = 0;
            $team->orders()->save($order);

            foreach ($data['items'] as $index => $item) {
                $variant = $variants->get($item['variant_id']);
                if ($variant === null || ! $variant->is_active || ! $variant->product->is_active) {
                    throw ValidationException::withMessages(["items.$index.variant_id" => 'El producto o la variante no están disponibles.']);
                }
                [$whole, $fraction] = explode('.', $variant->price);
                $price = ((int) $whole * 100) + (int) $fraction;
                $order->total_cents += $price * $item['quantity'];
                if ($order->total_cents > 99999999999999) {
                    throw ValidationException::withMessages(['items' => 'El total del pedido supera el máximo permitido.']);
                }
                $order->items()->create([
                    'product_variant_id' => $variant->id, 'product_name' => $variant->product->name,
                    'variant_name' => $variant->name, 'sku' => $variant->sku,
                    'quantity' => $item['quantity'], 'unit_price_cents' => $price,
                ]);
            }
            $order->save();

            return $order;
        }, attempts: 3);
    }
}
