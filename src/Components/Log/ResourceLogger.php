<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\Log;

use Exception;
use Psr\Log\AbstractLogger;
use Stringable;

final class ResourceLogger extends AbstractLogger
{
    /** @var null|resource */
    private $resource;

    /**
     * @param callable $formatter
     */
    public function __construct(
        private string $resourcePath,
        private $formatter = null
    ) {
        $this->formatter ??= [$this, 'defaultLineFormatter'];
    }

    private function openResource(): void
    {
        $resource = fopen($this->resourcePath, 'a+'); // a+ = reading and writing, pointer at EOF, create file if not exists
        if (! is_resource($resource)) {
            throw new Exception("Can't create resource for path '$this->resourcePath'.");
        }

        $this->resource = $resource;
    }

    /**
     * @param array<mixed> $context
     */
    private function defaultLineFormatter(string $level, string $message, array $context): string
    {
        $date = date('Y-m-d H:i:s');
        $line = "[$date] $level: $message";

        if ($context !== []) {
            $line .= ' ' . json_encode($context);
        }

        return $line;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, mixed> $context
     */
    // @phpstan-ignore-next-line ($param context without type specified, but if we specify one, it complains it is not covariant
    public function log($level, Stringable|string $message, array $context = []): void
    {
        if ($this->resource === null) {
            $this->openResource();
        }

        $formatter = $this->formatter;
        $line = $formatter($level, (string) $message, $context);

        fwrite($this->resource, $line . PHP_EOL); // @phpstan-ignore-line
    }

    public function __destruct()
    {
        if ($this->resource !== null) {
            fclose($this->resource);
        }
    }
}
