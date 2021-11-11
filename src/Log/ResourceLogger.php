<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Log;

use FlorentPoujol\SmolFramework\SmolFrameworkException;
use Psr\Log\AbstractLogger;
use Stringable;

final class ResourceLogger extends AbstractLogger
{
    /** @var null|resource */
    private $resource;

    public function __construct(
        private string $resourcePath
    ) {
    }

    private function openResource(): void
    {
        $resource = fopen($this->resourcePath, 'a+');
        if (! is_resource($resource)) {
            throw new SmolFrameworkException("Can't create resource for path '$this->resourcePath'.");
        }

        $this->resource = $resource;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, mixed> $context
     */
    // @phpstan-ignore-next-line ($param context without type specified, but if we specify one, it complains it is not covariant
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $date = date('Y-m-d H:i:s');
        $line = "[$date] $level: " . (string) $message;

        if ($context !== []) {
            $line .= ' ' . json_encode($context);
        }

        if ($this->resource === null) {
            $this->openResource();
        }

        fwrite($this->resource, $line . PHP_EOL); // @phpstan-ignore-line
    }

    public function __destruct()
    {
        if ($this->resource !== null) {
            fclose($this->resource);
        }
    }
}
