<?php

declare(strict_types=1);

use FlorentPoujol\Smol\Components\Log\ResourceLogger;
use FlorentPoujol\Smol\Site\app\LoggerFactory;

return [
    'default' => [
        'type' => ResourceLogger::class,
        'resourcePath' => 'writable/logs/errors.log',
        'formatter' => [LoggerFactory::class, 'errorLineFormatter'],
    ],

    'http-requests' => [

    ],
];
