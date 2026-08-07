<?php

return [
    'paths' => [resource_path('views')],
    'compiled' => env('VIEW_COMPILED_PATH', realpath(storage_path('framework/views'))),
    'relative_hash' => false,
    'compiled_extension' => 'php',
    'check_cache_timestamps' => true,
];
