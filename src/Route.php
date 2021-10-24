<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework;

use Nyholm\Psr7\Response;

final class Route
{
    /** @var array<string> */
    private array $methods;

    /** @var string Always with a leading slash, never with a trailing slash */
    private string $uri;
    private ?string $regexUri = null;
    /** @var array<string> */
    private array $placeholderNames = [];

    /** @var callable|string */
    private string|array|object $action; // @phpstan-ignore-line

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
        // turn a route like "/docs/{page}" with page [a-z-]+
        // into "/docs/([a-z-]+)"

        $placeholders = [];
        $regexes = [];

        $uriSegments = explode('/', $this->uri);
        foreach ($uriSegments as $segment) {
            $param = str_replace(['{', '}'], '', $segment);
            $this->placeholderNames[] = $param;

            if (str_starts_with($segment, '{') && ! in_array($segment, $placeholders, true)) {
                $placeholders[] = $segment;
                $regexes[] = '(' . ($this->placeholderRegexes[$param] ?? '[^/]+') . ')';
            }
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

        return preg_match('~^' . $this->regexUri . '$~', $actualUri) === 1;
    }

    public function getParamsFromUri(): array
    {
        if (! str_contains($this->uri, '{')) {
            return [];
        }

        $this->buildRegexUri();

        $matches = [];
        if (preg_match('~^' . $this->regexUri . '$~', $this->uri, $matches) !== 1) {
            return [];
        }

        array_shift($matches); // remove the whole match, to leave capturing group matches
        $assocMatches = [];

        foreach ($this->placeholderNames as $id => $name) {
            if (! isset($matches[$id]) || $matches[$id] === '') {
                // if the uri miss some optional placeholders
                // their captured value is empty string
                break;
            }

            $assocMatches[$name] = $matches[$id];
        }

        return $assocMatches;
    }

    public function callControllerAction(): Response
    {
        return call_user_func($this->action, ...$this->getParamsFromUri());
    }

    // public function callControllerAction(): ResponseInterface
    // {
    //     // get the parameters list based on the callable type
    //     /** @var callable $callable */
    //     $callable = $this->action;
    //
    //     $rFunc = null;
    //     if (is_string($callable)) {
    //         if (function_exists($callable)) {
    //             $rFunc = new \ReflectionFunction($callable);
    //         } elseif (str_contains($callable, '::')) {
    //             // Class::staticMethod
    //             $parts = explode('::', $callable);
    //             $rFunc = new ReflectionMethod($parts[0], $parts[1]);
    //         }
    //     } elseif (is_array($callable)) {
    //         // ["class", "staticMethod"] [$object, "method"]
    //         $rFunc = new ReflectionMethod($callable[0], $callable[1]);
    //     } elseif (is_object($callable)) {
    //         // invokable object or closure
    //         $rFunc = new ReflectionMethod($callable, '__invoke');
    //     }
    //
    //     $rParams = [];
    //     if ($rFunc instanceof ReflectionFunctionAbstract) {
    //         $rParams = $rFunc->getParameters();
    //     }
    //
    //     // build the argument list
    //     // this is needed because the callable's argument order
    //     // may not be the same in the uri
    //     $params = [];
    //     $paramsFromUri = $this->getParamsFromUri($this->rawUri);
    //     $paramDefaults = $this->getParamDefaults();
    //     foreach ($rParams as $rParam) {
    //         $name = $rParam->getName();
    //
    //         if (isset($paramsFromUri[$name])) {
    //             $params[] = $paramsFromUri[$name];
    //         } elseif (isset($paramDefaults[$name])) {
    //             $params[] = $paramDefaults[$name];
    //         } else {
    //             break;
    //             // do not set it to null so that the arg isn't passed at all to the target
    //             // and the callable applies the default value (hopefully) set in its signature
    //         }
    //     }
    //
    //     // finally, call the target
    //     return $callable(...$params);
    // }
}
