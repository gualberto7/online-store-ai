<?php

return [
    'enabled' => env('REPORTS_AI_ENABLED', true),
    'provider' => env('REPORTS_AI_PROVIDER', 'openai'),
    'model' => env('REPORTS_AI_MODEL', 'gpt-4.1-mini'),
];
