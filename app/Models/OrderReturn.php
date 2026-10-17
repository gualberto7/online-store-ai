<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\OrderReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $order_item_id
 * @property int|null $user_id
 * @property int|null $stock_movement_id
 * @property int $quantity
 * @property bool $restock
 * @property int $amount_cents
 * @property string $reason
 * @property string $request_token
 * @property PaymentMethod $payment_method
 * @property string|null $payment_reference
 * @property-read OrderItem $item
 */
#[Fillable(['quantity', 'restock', 'amount_cents', 'reason', 'request_token', 'payment_method', 'payment_reference'])]
class OrderReturn extends Model
{
    /** @use HasFactory<OrderReturnFactory> */
    use HasFactory;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'restock' => 'boolean', 'amount_cents' => 'integer', 'payment_method' => PaymentMethod::class];
    }
}
