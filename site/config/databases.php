<?php

declare(strict_types=1);

use function FlorentPoujol\Smol\Infrastructure\env;

return [
    'db1' => [
        'dsn' => env('DB1_DSN'),
    ],

    'db2' => [
        'dsn' => env('DB2_DSN'),
    ],
];
