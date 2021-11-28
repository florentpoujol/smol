<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Framework;

use FlorentPoujol\SmolFramework\Components\Container\Container;
use FlorentPoujol\SmolFramework\Components\Events\EventDispatcher;

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

    /** @var array<class-string<ServiceProviderInterface>|ServiceProviderInterface> */
    private array $serviceProviders = [];

    /**
     * @param array<ServiceProviderInterface> $serviceProviders
     */
    public function setServiceProviders(array $serviceProviders): void
    {
        $this->serviceProviders = $serviceProviders;
    }

    private EventDispatcher $eventDispatcher;

    public function register(): void
    {
        $this->container = new $this->config['container_fqcn']();
        $this->container->setInstance(self::class, $this);

        $this->eventDispatcher = $this->container->get(EventDispatcher::class);
        $this->eventDispatcher->dispatch('framework.before-register');

        foreach ($this->config as $key => $value) {
            $this->container->setParameter($key, $value);
        }

        foreach ($this->serviceProviders as $i => $serviceProvider) {
            $this->serviceProviders[$i] = $this->container->get($serviceProvider);

            $this->serviceProviders[$i]->register($this->container);
        }

        $this->eventDispatcher->dispatch('framework.after-register');
    }

    public function boot(): void
    {
        $this->eventDispatcher->dispatch('framework.before-boot');

        foreach ($this->serviceProviders as $serviceProvider) {
            $serviceProvider->boot();
        }

        $this->eventDispatcher->dispatch('framework.after-boot');
    }

    public function stop(): void
    {
        $this->eventDispatcher->dispatch('framework.before-stop');

        foreach ($this->serviceProviders as $serviceProvider) {
            $serviceProvider->stop();
        }

        $this->eventDispatcher->dispatch('framework.after-stop');
    }
}
