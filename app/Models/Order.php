<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $team_id
 * @property int $branch_id
 * @property int|null $user_id
 * @property string|null $customer_name
 * @property string|null $notes
 * @property OrderStatus $status
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property string|null $checkout_token
 * @property int $total_cents
 * @property-read Team $team
 * @property-read Branch $branch
 * @property-read Collection<int, OrderItem> $items
 */
#[Fillable(['customer_name', 'notes'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => OrderStatus::class, 'total_cents' => 'integer', 'confirmed_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }
}
