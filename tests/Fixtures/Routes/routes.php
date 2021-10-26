<?php

declare(strict_types=1);

use FlorentPoujol\SimplePhpFramework\Route;

return [
    new Route('get', '/get', function (): void { }),
    new Route('put', '/put', function (): void { }),
    new Route('post', '/post', function (): void { }),
];
