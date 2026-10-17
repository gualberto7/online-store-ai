<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderReturn> */
class OrderReturnFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['order_id' => Order::factory()->state(['status' => OrderStatus::Confirmed, 'completed_at' => now(), 'payment_method' => PaymentMethod::Cash, 'total_cents' => 10000]),
            'order_item_id' => function (array $attributes): int {
                $order = Order::query()->whereKey($attributes['order_id'])->sole();
                $variant = ProductVariant::factory()->create(['team_id' => $order->team_id,
                    'product_id' => Product::factory()->create(['team_id' => $order->team_id])->id]);

                return $order->items()->create(['product_variant_id' => $variant->id, 'product_name' => $variant->product->name,
                    'variant_name' => $variant->name, 'sku' => $variant->sku, 'quantity' => 1, 'unit_price_cents' => 10000])->id;
            },
            'quantity' => 1, 'restock' => false, 'amount_cents' => 10000, 'payment_method' => PaymentMethod::Cash,
            'reason' => 'Devolución de prueba', 'request_token' => fake()->uuid()];
    }
}
