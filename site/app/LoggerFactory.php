<?php

declare(strict_types=1);

namespace FlorentPoujol\Smol\Site\app;

use Exception;
use FlorentPoujol\Smol\Components\Config\ConfigRepository;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    /** @var array<string, LoggerInterface> */
    private array $loggers;

    public function __construct(
        private ConfigRepository $config
    ) {
    }

    public function make(string $logger): LoggerInterface
    {
        if (isset($this->loggers[$logger])) {
            return $this->loggers[$logger];
        }

        $config = $this->config->get("loggers.$logger");
        if ($config === null) {
            throw new Exception();
        }

        /** @var class-string<AbstractLogger> $loggerFqcn */
        $loggerFqcn = $config['type'];
        unset($config['type']);

        $this->loggers[$logger] = new $loggerFqcn(...$config);

        return $this->loggers[$logger];
    }

    public static function errorLineFormatter(string $level, string $message, array $context): string
    {
        return $message;
    }
}

// function index(LoggerFactory $loggerFactory)
// {
//     $errorLogger = $loggerFactory->make('error');
//     $errorLogger->warning();
// }
