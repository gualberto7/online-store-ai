<?php

namespace App\Actions;

use App\Models\ProductVariant;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StorefrontCart
{
    public function __construct(private Request $request) {}

    public function team(): ?Team
    {
        $id = filter_var(config('storefront.team_id'), FILTER_VALIDATE_INT);

        return $id === false ? null : Team::query()->whereKey($id)->where('is_personal', false)->first();
    }

    public function key(): string
    {
        return 'storefront_cart.'.($this->team()->id ?? 0);
    }

    /** @return array<int, int> */
    public function quantities(): array
    {
        return $this->request->session()->get($this->key().'.items', []);
    }

    public function set(int $variantId, int $quantity): void
    {
        $items = $this->quantities();
        if ($quantity === 0) {
            unset($items[$variantId]);
        } else {
            $items[$variantId] = $quantity;
        }
        $this->request->session()->put($this->key().'.items', $items);
    }

    /** @return Collection<int, ProductVariant> */
    public function variants(): Collection
    {
        return ProductVariant::query()->where('team_id', $this->team()->id ?? 0)
            ->whereIn('id', array_keys($this->quantities()))->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->publiclyVisible())
            ->with(['product.images' => fn ($query) => $query->limit(1), 'stocks' => fn ($query) => $query->whereHas('branch', fn ($query) => $query->where('is_active', true))])->get();
    }

    /** @return array{items: array<int, array<string, mixed>>, total_cents: int, unavailable: bool} */
    public function summary(): array
    {
        $quantities = $this->quantities();
        $items = $this->variants()->map(function (ProductVariant $variant) use ($quantities): array {
            [$whole, $fraction] = explode('.', $variant->price);
            $price = (int) $whole * 100 + (int) $fraction;
            $quantity = $quantities[$variant->id];

            return ['id' => $variant->id, 'product_id' => $variant->product_id, 'name' => $variant->product->name,
                'variant' => $variant->name, 'image' => $variant->product->images->first()?->url,
                'quantity' => $quantity, 'unit_price_cents' => $price, 'subtotal_cents' => $price * $quantity,
                'available_at' => $variant->stocks->where('quantity', '>=', $quantity)->pluck('branch_id')->values()->all()];
        })->values()->all();

        return ['items' => $items, 'total_cents' => array_sum(array_column($items, 'subtotal_cents')), 'unavailable' => count($items) !== count($quantities)];
    }
}
