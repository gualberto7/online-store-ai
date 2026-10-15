<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $order_id
 * @property int $id
 * @property int $stock_id
 * @property int|null $user_id
 * @property string $type
 * @property int $quantity_change
 * @property int $balance_before
 * @property int $balance_after
 * @property string $reason
 */
#[Fillable(['user_id', 'type', 'quantity_change', 'balance_before', 'balance_after', 'reason'])]
class StockMovement extends Model
{
    /** @return BelongsTo<Stock, $this> */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity_change' => 'integer', 'balance_before' => 'integer', 'balance_after' => 'integer'];
    }
}
