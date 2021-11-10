<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework;

use Psr\Http\Server\MiddlewareInterface;

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

    /** @var array<string> */
    private array $placeholderRegexes;

    private ?string $name;

    /**
     * @param array<string>|string  $methods            Http method(s)
     * @param array<string, string> $placeholderRegexes keys are placeholder names, values are regex
     */
    public function __construct(
        array|string $methods,
        string $uri,
        callable|string $action,
        array $placeholderRegexes = [],
        ?string $name = null,
    ) {
        $this->methods = array_map('strtoupper', (array) $methods);
        $this->uri = '/' . trim($uri, ' /'); // space + /
        $this->action = $action; // @phpstan-ignore-line
        $this->placeholderRegexes = $placeholderRegexes;
        $this->name = $name;
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
        // into "/docs/(?<page>[a-z-]+)"

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

        // since the exact comparison doesn't match, this route is either complitely different
        // or more likely has the same prefix (since the router only call match() for routes with a matching prefix), but has regex segments

        $this->buildRegexUri();

        $actionArguments = [];
        if (preg_match('~^' . $this->regexUri . '$~', $actualUri, $actionArguments) !== 1) {
            return false;
        }

        // remove integer keys
        foreach ($actionArguments as $key => $value) {
            if (is_int($key)) {
                unset($actionArguments[$key]);
            }
        }

        $this->actionArguments = $actionArguments;

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

    /** @var array<callable|string|class-string<\Psr\Http\Server\MiddlewareInterface>> */
    private array $middleware = [];

    /**
     * Add one **or several** middleware.
     *
     * @param array<callable|string|class-string<\Psr\Http\Server\MiddlewareInterface>> $middleware
     */
    public function setMiddleware(array $middleware): self
    {
        $this->middleware = $middleware;

        return $this;
    }

    /**
     * @return array<callable|string|class-string<\Psr\Http\Server\MiddlewareInterface>>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function hasPsr15Middleware(): bool
    {
        $mid = $this->middleware[0] ?? null;

        return
            is_string($mid)
            && class_exists($mid)
            && is_array(class_implements($mid))
            && in_array(MiddlewareInterface::class, class_implements($mid), true);
    }
}
