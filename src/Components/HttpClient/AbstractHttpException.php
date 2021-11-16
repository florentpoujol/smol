<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\HttpClient;

use Exception;
use Psr\Http\Client\ClientExceptionInterface;

abstract class AbstractHttpException extends Exception implements ClientExceptionInterface
{
}