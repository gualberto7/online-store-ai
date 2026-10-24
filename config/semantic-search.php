<?php

return [
    'enabled' => env('SEMANTIC_SEARCH_ENABLED', false),
    'provider' => env('SEMANTIC_SEARCH_PROVIDER', ''),
    'model' => env('SEMANTIC_SEARCH_MODEL', ''),
    'dimensions' => (int) env('SEMANTIC_SEARCH_DIMENSIONS', 1536),
    'min_similarity' => 0.4,
    'queue_connection' => env('SEMANTIC_SEARCH_QUEUE_CONNECTION', 'database'),
];
