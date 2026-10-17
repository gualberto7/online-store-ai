<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorefrontCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['customer_name' => ['required', 'string', 'max:255'], 'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:40'], 'notes' => ['nullable', 'string', 'max:2000'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('team_id', config('storefront.team_id'))->where('is_active', true)],
            'token' => ['required', 'uuid', Rule::in($this->session()->get('storefront_checkout_tokens', []))]];
    }
}
