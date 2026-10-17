<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStorefrontCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['variant_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:0', 'max:1000']];
    }
}
