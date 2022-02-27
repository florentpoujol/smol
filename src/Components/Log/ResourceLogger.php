<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Components\Log;

use Exception;
use Psr\Log\AbstractLogger;
use Stringable;

final class ResourceLogger extends AbstractLogger
{
    /** @var null|resource */
    private $resource;

    /** @var callable */
    private $formatter;

    public function __construct(
        private string $resourcePath,
        callable $formatter = null
    ) {
        $this->formatter = $formatter ?? [$this, 'defaultLineFormatter'];
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

    /**
     * @return array<string, callable|string>
     *
     * @throws Exception If the formatter is a closure
     */
    public function __serialize(): array
    {
        $this->__destruct();

        if ($this->formatter instanceof \Closure) {
            throw new Exception();
        }

        return [
            'resourcePath' => $this->resourcePath,
            'formatter' => $this->formatter,
        ];
    }

    public function __destruct()
    {
        if ($this->resource !== null) {
            fclose($this->resource);
        }
    }
}
