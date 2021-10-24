<?php

declare(strict_types=1);

namespace FlorentPoujol\SimplePhpFramework;

use Closure;
use Psr\Http\Message\ResponseInterface;
use ReflectionFunctionAbstract;
use ReflectionMethod;

final class Route
{
    /** @var array<string> */
    private array $methods = [];
    /** @var array<string> */
    private array $paramNames = [];

    private string $rawUri = '';
    private string $regexUri = '';

    /** @var callable */
    private Closure|array|string $action; // @phpstan-ignore-line

    /** @var array<string, string> */
    private array $paramConstraints = [];
    /** @var array<string, mixed> */
    private array $paramDefaults = [];

    /**
     * @param string|array<string>  $methods
     * @param array<string, string> $paramConditions
     * @param array<string, mixed>  $paramDefaultValues
     */
    public function __construct(
        string|array $methods,
        string $uri,
        callable $action,
        array $paramConditions = [],
        array $paramDefaultValues = []
    ) {
        if (is_string($methods)) {
            $methods = [$methods];
        }

        $this->methods = array_map('strtolower', $methods);
        $this->action = $action; // @phpstan-ignore-line
        $this->paramDefaults = $paramDefaultValues;

        if (count($paramConditions) > 0) {
            $this->paramConstraints = $paramConditions;
        }

        $this->setUri($uri);
    }

    /**
     * @return array<string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @param array<mixed> $args
     */
    public function getUri(array $args = null): string
    {
        if ($args === null) {
            return $this->rawUri;
        }

        $uri = $this->rawUri;
        foreach ($args as $name => $value) {
            $search = ['{' . $name . '}', "[$name]"];
            $uri = str_replace($search, $value, $uri);
        }

        // suppose that the remaining placeholders are not needed
        // so only return the uri up to the first bracket
        $pos = strpos($uri, '[');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }

        return $uri;
    }

    /**
     * Take the provided URI definition, and turn it to a regex to be easilly matched against the actual URI.
     */
    private function setUri(string $uri): void
    {
        $this->rawUri = $uri;

        // render all slashes optional, if the uri has a placeholder
        $pos = strpos($uri, '[');
        if ($pos !== false) {
            $subUri = substr($uri, $pos - 1); // part of the uri in which work on slashes
            $uri = str_replace(
                $subUri,
                str_replace('/', '/?', $subUri),
                $uri
            );
        }

        // make sure the uri ends with an optional trailing slash
        if (! str_ends_with($uri, '/?')) {
            $uri .= '/?';
        }

        // look for optional placeholder
        $matches = [];
        if (preg_match_all("/\[([^\]]+)\]/", $uri, $matches) > 0) {
            foreach ($matches[1] as $id => $varName) {
                $this->paramNames[] = $varName;

                $constraint = '[^/&]+';
                if (isset($this->paramConstraints[$varName])) {
                    $constraint = $this->paramConstraints[$varName];
                }

                $uri = str_replace("[$varName]", "($constraint)?", $uri);
            }
        }

        // look for named placeholder
        $matches = [];
        if (preg_match_all('/{([^}]+)}/', $uri, $matches) > 0) {
            foreach ($matches[1] as $id => $varName) {
                $this->paramNames[] = $varName;

                $constraint = '[^/&]+';
                if (isset($this->paramConstraints[$varName])) {
                    $constraint = $this->paramConstraints[$varName];
                }

                $uri = str_replace('{' . $varName . '}', "($constraint)", $uri);
            }
        }

        $this->regexUri = $uri;
    }

    public function getAction(): callable
    {
        return $this->action; // @phpstan-ignore-line
    }

    /**
     * @return array<string, string>
     */
    public function getParamConstraints(): array
    {
        return $this->paramConstraints;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParamDefaults(): array
    {
        return $this->paramDefaults;
    }

    /**
     * Tell whether the route match the specified method and uri or not.
     */
    public function match(string $method, string $uri): bool
    {
        return
            ! in_array(strtolower($method), $this->methods, true) ||
            preg_match('~^' . $this->regexUri . '$~', $uri) === 1;
    }

    /**
     * Return the captured placeholders from the uri as an associative array.
     * Missing placeholders from the uri are not present at all in the returned array.
     *
     * @return array<string, mixed>
     */
    public function getParamsFromUri(string $uri): array
    {
        $matches = [];
        if (preg_match('~^' . $this->regexUri . '$~', $uri, $matches) !== 1) {
            return [];
        }

        $assocMatches = [];
        if (count($matches) === 1) {
            // no placeholder capture, just the whole uri match
            return $assocMatches;
        }

        array_shift($matches);
        foreach ($this->paramNames as $id => $name) {
            if ($matches[$id] === '') {
                // if the uri miss some optional placeholders
                // their captured value is empty string
                break;
            }
            $assocMatches[$name] = $matches[$id];
        }

        return $assocMatches;
    }

    public function callControllerAction(): ResponseInterface
    {
        // get the parameters list based on the callable type
        /** @var callable $callable */
        $callable = $this->action;

        $rFunc = null;
        if (is_string($callable)) {
            if (function_exists($callable)) {
                $rFunc = new \ReflectionFunction($callable);
            } elseif (str_contains($callable, '::')) {
                // Class::staticMethod
                $parts = explode('::', $callable);
                $rFunc = new ReflectionMethod($parts[0], $parts[1]);
            }
        } elseif (is_array($callable)) {
            // ["class", "staticMethod"] [$object, "method"]
            $rFunc = new ReflectionMethod($callable[0], $callable[1]);
        } elseif (is_object($callable)) {
            // invokable object or closure
            $rFunc = new ReflectionMethod($callable, '__invoke');
        }

        $rParams = [];
        if ($rFunc instanceof ReflectionFunctionAbstract) {
            $rParams = $rFunc->getParameters();
        }

        // build the argument list
        // this is needed because the callable's argument order
        // may not be the same in the uri
        $params = [];
        $paramsFromUri = $this->getParamsFromUri($this->rawUri);
        $paramDefaults = $this->getParamDefaults();
        foreach ($rParams as $rParam) {
            $name = $rParam->getName();

            if (isset($paramsFromUri[$name])) {
                $params[] = $paramsFromUri[$name];
            } elseif (isset($paramDefaults[$name])) {
                $params[] = $paramDefaults[$name];
            } else {
                break;
                // do not set it to null so that the arg isn't passed at all to the target
                // and the callable applies the default value (hopefully) set in its signature
            }
        }

        // finally, call the target
        return $callable(...$params);
    }
}
