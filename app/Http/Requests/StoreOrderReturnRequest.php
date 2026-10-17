<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && ($this->user()?->can('manage', $order) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['order_item_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'restock' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:500'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)], 'payment_reference' => ['nullable', 'string', 'max:255'],
            'request_token' => ['required', 'uuid']];
    }
}
