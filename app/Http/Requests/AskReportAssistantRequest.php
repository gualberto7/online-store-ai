<?php

namespace App\Http\Requests;

class AskReportAssistantRequest extends BrowseReportsRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [...parent::rules(), 'prompt' => ['required', 'string', 'max:2000']];
    }
}
