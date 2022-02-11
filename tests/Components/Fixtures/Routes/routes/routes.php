<?php

declare(strict_types=1);

use FlorentPoujol\Smol\Infrastructure\Http\Route;
use FlorentPoujol\Smol\Tests\Fixtures\Routes\TestMiddleware1;
use FlorentPoujol\Smol\Tests\Fixtures\Routes\TestMiddleware2;

return [
    new Route('get', '/get/static-route', 'nothing'),
    new Route(['POST', 'put'], '/postput/static-route', 'nothing'),

    new Route('get', '/docs/{page}', 'nothing', name: 'dynamic doc page'),
    new Route('get', '/docs/page', 'nothing', name: 'static doc page'),
    new Route('get', '/docs/{page}/{page}', 'nothing'),

    new Route('get', '/redirect/302', 'redirect:/somewhere'),
    new Route('get', '/redirect/301', 'redirect-permanent:/somewhere-else'),

    (new Route('get', '/middleware', 'nothing'))
        ->setMiddleware([
            TestMiddleware1::class,
            TestMiddleware2::class,
        ]),
];
