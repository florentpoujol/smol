<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Framework;

use FlorentPoujol\SmolFramework\Components\Container\Container;
use FlorentPoujol\SmolFramework\Framework\RequestHandlers\FpmRequestHandler;

final class Framework
{
    private static self $instance;

    /**
     * @param array<string, null|string> $config
     */
    public static function make(array $config): self
    {
        return new self($config);
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    /**
     * @param array<string, null|string> $config
     */
    public function __construct(array $config)
    {
        self::$instance = $this;

        $this->config = array_merge($this->config, $config);
    }

    // --------------------------------------------------
    // config stuffs

    /** @var array<string, null|string> */
    private array $config = [
        'baseAppPath' => __DIR__,
        'container_fqcn' => Container::class,
        'environment' => 'production',
    ];

    public function getConfig(string $key, string $default = null): ?string
    {
        return $this->config[$key] ?? $default;
    }

    public function setConfig(string $key, ?string $value): self
    {
        $this->config[$key] = $value;

        return $this;
    }

    // --------------------------------------------------
    // boot stuffs

    private Container $container;

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function register(): void
    {
        $this->container = new $this->config['container_fqcn']();
        $this->container->setInstance(self::class, $this);

        foreach ($this->config as $key => $value) {
            $this->container->setParameter($key, $value);
        }

        // loop on plugins
    }

    public function boot(): void
    {
        // loop on plugins
    }

    public function run(): void
    {
        /** @var FpmRequestHandler $reqeustHandler */
        $requestHandler = $this->container->get(FpmRequestHandler::class);
        $requestHandler->handle();

        // clean up
    }

    public function cleanUp(): void
    {
        // loop on plugin
    }
}
