<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrowseStorefrontRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $teamId = config('storefront.team_id');

        return [
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('team_id', $teamId)->where('is_active', true)],
            'branch' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('team_id', $teamId)->where('is_active', true)],
            'available' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['name', 'price_asc', 'price_desc'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
