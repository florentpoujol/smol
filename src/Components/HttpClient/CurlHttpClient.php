<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\HttpClient;

use CurlHandle;
use FlorentPoujol\SmolFramework\Infrastructure\Exceptions\SmolFrameworkException;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Depends on the Curl exception.
 */
final class CurlHttpClient implements ClientInterface
{
    private CurlHandle $curlHandle;

    public function getCurlHandle(): CurlHandle
    {
        return $this->curlHandle;
    }

    /** @var array<int, mixed> Keys must be from the CURLOPT_* constants. */
    private array $options = [
        CURLOPT_USERAGENT => 'Smol Framework/v0.1.0',
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 10,
    ];

    /**
     * @param array<int, mixed> $options keys must be from the CURLOPT_* constants
     */
    public function setOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /** @var null|callable */
    private $beforeSendHook = null;

    public function setBeforeSendHook(callable $beforeSendHook): self
    {
        $this->beforeSendHook = $beforeSendHook;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $uri = (string) $request->getUri();
        $curlHandle = curl_init($uri);
        if (! ($curlHandle instanceof CurlHandle)) {
            throw new SmolFrameworkException("Couldn't create the CURL handle for uri '$uri'.");
        }
        $this->curlHandle = $curlHandle;

        $this->setOptionsFromRequest($request);

        $success = curl_setopt_array($curlHandle, $this->options);
        if (! $success) {
            throw new SmolFrameworkException('Some CURL options could not be set.');
        }

        if (
            (
                isset($this->options[CURLOPT_POST], $this->options[CURLOPT_PUT])
                || ($this->options[CURLOPT_CUSTOMREQUEST] ?? null) === 'PATCH'
            )
            && ! isset($this->options[CURLOPT_POSTFIELDS])
        ) {
            // POST or PUT or PATCH
            // and no body is set yet
            if ($request->getBody()->isSeekable()) {
                $request->getBody()->rewind();
            }

            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $request->getBody()->getContents());
        }

        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_HEADER, false);

        if (is_callable($this->beforeSendHook)) {
            ($this->beforeSendHook)($this->curlHandle, $request);
        }

        $body = curl_exec($curlHandle);

        curl_close($curlHandle);

        if (! is_string($body)) {
            throw (new RequestException())->setRequest($request);
        }

        $headersAsString = curl_getinfo($curlHandle, CURLINFO_HEADER_OUT);
        $headers = explode(PHP_EOL, $headersAsString);
        foreach ($headers as $line) {
            [$name, $values] = explode(': ', $line, 2); // @phpstan-ignore-line (Parameter #2 $string of function explode expects string, array<int, string>|string given.)
            $headers[$name] = explode(', ', $values);
        }

        return new Response(
            curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE),
            $headers,
            $body,
            curl_getinfo($curlHandle, CURLINFO_HTTP_VERSION)
        );
    }

    private function setOptionsFromRequest(RequestInterface $request): void
    {
        $this->options[CURLOPT_HTTP_VERSION] = $request->getProtocolVersion();

        switch (strtoupper($request->getMethod())) {
            default: // GET
                $this->options[CURLOPT_HTTPGET] = true;
                $this->options[CURLOPT_NOBODY] = true;
                break;
            case 'POST':
                $this->options[CURLOPT_POST] = true;
                break;
            case 'PUT':
                $this->options[CURLOPT_PUT] = true;
                break;
            case 'PATCH':
                $this->options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
                break;
            case 'HEAD':
            case 'OPTIONS':
            case 'DELETE':
                $this->options[CURLOPT_CUSTOMREQUEST] = strtoupper($request->getMethod());
                $this->options[CURLOPT_NOBODY] = true;
                break;
        }

        $contentTypeFound = false;
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[] = $name . ': ' . implode(', ', $values);

            if (strtolower($name) === 'content-type') {
                $contentTypeFound = true;
            }
        }

        if (! $contentTypeFound) {
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Accept: application/json';
        }

        $this->options[CURLOPT_HTTPHEADER] = $headers;
    }
}
