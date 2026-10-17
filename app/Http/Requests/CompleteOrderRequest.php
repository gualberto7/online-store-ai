<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && ($this->user()?->can('manage', $order) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['payment_method' => ['required', Rule::enum(PaymentMethod::class)], 'payment_reference' => ['nullable', 'string', 'max:255']];
    }
}
