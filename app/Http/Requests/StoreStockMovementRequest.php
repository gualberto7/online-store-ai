<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('branch'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $branch = $this->route('branch');
        abort_unless($branch instanceof Branch, 404);

        return [
            'variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->where('team_id', $branch->team_id)],
            'type' => ['required', Rule::in(['receipt', 'issue', 'adjustment'])],
            'quantity' => ['required', 'integer', 'max:2147483647', $this->input('type') === 'adjustment' ? 'min:0' : 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
