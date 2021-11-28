<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Framework;

use FlorentPoujol\SmolFramework\Components\Container\Container;
use FlorentPoujol\SmolFramework\Framework\RequestHandlers\RequestHandlerInterface;

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

    /** @var array<ServiceProviderInterface> */
    private array $serviceProviders = [];

    /**
     * @param array<ServiceProviderInterface> $serviceProviders
     */
    public function setServiceProviders(array $serviceProviders): void
    {
        $this->serviceProviders = $serviceProviders;
    }

    public function run(): void
    {
        $this->container = new $this->config['container_fqcn']();
        $this->container->setInstance(self::class, $this);

        foreach ($this->config as $key => $value) {
            $this->container->setParameter($key, $value);
        }

        $bootCallable = [];
        foreach ($this->serviceProviders as $serviceProvider) {
            if (is_callable($serviceProvider)) {
                $serviceProvider($this->container);
                $bootCallable[] = $serviceProvider;

                continue;
            }

            $instance = $this->container->get($serviceProvider);
            $register = [$instance, 'register'];
            assert(is_callable($register));
            $register($this->container);

            $boot = [$instance, 'boot'];
            assert(is_callable($boot));
            $bootCallable[] = $boot;
        }

        foreach ($bootCallable as $callable) {
            $callable();
        }

        /** @var RequestHandlerInterface $requestHandler */
        $requestHandler = $this->container->get(RequestHandlerInterface::class);
        $requestHandler->handle();
    }
}
