<?php

declare(strict_types=1);

use function FlorentPoujol\SmolFramework\Framework\env;

return [
    'key' => 'file1',
    'array_key' => [
        'key' => true,
    ],
    'from_env' => env('ENV_VAR_1'),
    'from_env_with_default' => env('ENV_VAR_2', 'env var 2 default value'),
    'from_non_existing_env_without_default' => env('ENV_VAR_3'),
];
