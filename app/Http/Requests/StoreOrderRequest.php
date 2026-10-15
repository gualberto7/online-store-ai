<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Order::class, $this->route('current_team')]);
    }

    /** @return array{branch_id: int, customer_name: string|null, notes: string|null, items: array<int, array{variant_id: int, quantity: int}>} */
    public function orderData(): array
    {
        $items = [];
        foreach (array_keys($this->validated('items')) as $index) {
            $items[] = [
                'variant_id' => $this->integer("items.$index.variant_id"),
                'quantity' => $this->integer("items.$index.quantity"),
            ];
        }

        return [
            'branch_id' => $this->integer('branch_id'),
            'customer_name' => $this->filled('customer_name') ? $this->string('customer_name')->toString() : null,
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
            'items' => $items,
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $team = $this->route('current_team');
        abort_unless($team instanceof Team, 404);

        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('team_id', $team->id)->where('is_active', true)],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:variant_id,quantity'],
            'items.*.variant_id' => ['required', 'integer', 'distinct', Rule::exists('product_variants', 'id')->where('team_id', $team->id)->where('is_active', true)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
