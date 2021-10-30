<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework\HttpClient;

use Exception;
use Psr\Http\Client\ClientExceptionInterface;

abstract class AbstractHttpException extends Exception implements ClientExceptionInterface
{
}
