<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrowseOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team && ($this->user()?->can('viewAny', [Order::class, $team]) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $team = $this->route('current_team');

        return [
            'q' => ['nullable', 'string', 'max:120'],
            'branch' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('team_id', $team instanceof Team ? $team->id : 0)],
            'status' => ['nullable', Rule::in(['draft', 'pending', 'completed', 'cancelled'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
