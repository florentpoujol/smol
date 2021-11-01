<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Container;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class ContainerException extends Exception implements NotFoundExceptionInterface // extended by NotFoundException
{
}
