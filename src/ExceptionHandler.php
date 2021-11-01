<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

final class ExceptionHandler
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function render(Throwable $exception): ResponseInterface
    {
        $whoops = new Run();
        $whoops->allowQuit(false);
        $whoops->writeToOutput(false);
        $whoops->pushHandler(new PrettyPageHandler());

        $html = $whoops->handleException($exception);

        return new Response(500, [], $html);
    }

    public function report(Throwable $exception): void
    {
        $message = get_class($exception) . ': ' . $exception->getMessage();
        $context = [
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTrace(),
        ];
        $this->logger->error($message, $context);
    }
}
