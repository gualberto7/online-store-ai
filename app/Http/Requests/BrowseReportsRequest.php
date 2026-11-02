<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrowseReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team && ($this->user()?->can('viewReports', $team) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['from' => $this->input('from') ?: now()->startOfMonth()->toDateString(), 'to' => $this->input('to') ?: now()->toDateString()]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $team = $this->route('current_team');

        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'branch' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('team_id', $team instanceof Team ? $team->id : 0)],
            'view' => ['nullable', Rule::in(['details', 'charts', 'ai'])],
            'stock' => ['nullable', Rule::in(['all', 'empty'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'type' => ['nullable', Rule::in(['sales', 'returns', 'inventory', 'products'])],
        ];
    }
}
