<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Framework\RequestHandlers;

use FlorentPoujol\SmolFramework\Framework\Http\Route;
use Psr\Http\Message\ResponseInterface;

interface RequestHandlerInterface
{
    public function handle(): void;

    /**
     * @return never-return
     */
    public function handleRequestThroughPsr15Middleware(): void;

    public function callRouteAction(Route $route): ResponseInterface;

    /**
     * @return never-return
     */
    public function sendResponseToClient(ResponseInterface $response): void;
}
