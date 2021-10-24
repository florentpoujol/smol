<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework;

final class Route
{
    /** @var array<string> */
    private array $methods;

    /** @var string Always with a leading slash, never with a trailing slash */
    private string $uri;
    private ?string $regexUri = null;

    /** @var callable|string */
    private string|array|object $action; // @phpstan-ignore-line
    /** @var array<string, string> */
    private array $actionArguments = [];

    /**
     * @param array<string>|string  $methods            Http method(s)
     * @param array<string, string> $placeholderRegexes keys are placeholder names, values are regex
     */
    public function __construct(
        array|string $methods,
        string $uri,
        callable|string $action,
        private array $placeholderRegexes = [],
        private ?string $name = null
    ) {
        $this->methods = array_map('strtoupper', (array) $methods);
        $this->uri = '/' . trim($uri, ' /'); // space + /
        $this->action = $action; // @phpstan-ignore-line
    }

    /**
     * @return array<string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getAction(): callable|string
    {
        return $this->action; // @phpstan-ignore-line
    }

    /**
     * @return array<string, string>
     */
    public function getPlaceholderRegexes(): array
    {
        return $this->placeholderRegexes;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    private function buildRegexUri(): void
    {
        if ($this->regexUri !== null) {
            return;
        }
        // turn a route like "/docs/{page}" with page [a-z-]+
        // into "/docs/([a-z-]+)"

        $placeholders = [];
        $regexes = [];

        $uriSegments = explode('/', $this->uri);
        foreach ($uriSegments as $segment) {
            if (! str_starts_with($segment, '{')) {
                continue;
            }

            $placeholderName = str_replace(['{', '}'], '', $segment);

            $placeholders[] = $segment;
            $regex = $this->placeholderRegexes[$placeholderName] ?? '[^/]+';
            $regexes[] = "(?<$placeholderName>$regex)"; // this is a named capturing group
        }

        $this->regexUri = str_replace($placeholders, $regexes, $this->uri);
    }

    public function match(string $actualUri): bool
    {
        if ($this->uri === $actualUri) {
            return true;
        }

        if (! str_contains($this->uri, '{')) {
            // note that this never really happen since we are only calling match() for routes with the same prefix
            // so if the exact comparison isn't true, then the route must have a placeholder
            return false;
        }

        $this->buildRegexUri();

        $matches = [];
        $match = preg_match('~^' . $this->regexUri . '$~', $actualUri, $matches) === 1;
        if (! $match) {
            return false;
        }

        // remove integer keys
        foreach ($matches as $key => $value) {
            if (is_int($key)) {
                unset($matches[$key]);
            }
        }

        $this->actionArguments = $matches;

        return true;
    }

    /**
     * @return array<string, string>
     */
    public function getActionArguments(): array
    {
        return $this->actionArguments;
    }

    public function isRedirect(): bool
    {
        return is_string($this->action) && str_starts_with($this->action, 'redirect');
    }

    // --------------------------------------------------
    // middleware stuffs

    /** @var array<callable|string> */
    private array $middleware = [];

    /**
     * @param array<callable|string> $middleware
     */
    public function addMiddleware(array $middleware): void
    {
        $this->middleware = array_merge($this->middleware, $middleware);
    }

    /**
     * @return array<callable|string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
