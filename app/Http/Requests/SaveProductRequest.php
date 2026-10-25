<?php

namespace App\Http\Requests;

use App\Enums\ProductAudience;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', [Product::class, $this->route('current_team')]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $team = $this->route('current_team');
        abort_unless($team instanceof Team, 404);

        return [
            'audience' => ['sometimes', 'required', Rule::enum(ProductAudience::class)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['required', 'boolean'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('team_id', $team->id)],
        ];
    }
}
