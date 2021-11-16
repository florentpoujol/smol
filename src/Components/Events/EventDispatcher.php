<?php

declare(strict_types=1);

namespace FlorentPoujol\SmolFramework\Components\Events;

use FlorentPoujol\SmolFramework\Components\Container\Container;

final class EventDispatcher
{
    /**
     * @var array<string, array<int, array<callable|string>>> eventName => [priority => [listeners]]
     */
    private array $listeners = [];

    public function __construct(
        private Container $container
    ) {
    }

    /**
     * @param callable|string $listener A callable or "at string"
     */
    public function addListener(string $eventName, callable|string $listener, int $priority = 0): void
    {
        $this->listeners[$eventName] ??= [];
        $this->listeners[$eventName][$priority] ??= [];
        $this->listeners[$eventName][$priority][] = $listener;
    }

    /**
     * @param null|callable|string $listener A callable or "at string"
     */
    public function removeListener(string $eventName, callable|string $listener = null): void
    {
        if ($listener === null) {
            unset($this->listeners[$eventName]);

            return;
        }

        if (! isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $priority => $listeners) {
            $offset = array_search($listener, $listeners, true);
            if ($offset !== false) {
                array_splice($this->listeners[$eventName][$priority], (int) $offset, 1);

                return;
            }
        }
    }

    public function hasListener(string $eventName): bool
    {
        if (! isset($this->listeners[$eventName])) {
            return false;
        }

        foreach ($this->listeners[$eventName] as $listeners) {
            if ($listeners !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<callable>
     */
    public function getListenersForEvent(string $eventName): array
    {
        if (! isset($this->listeners[$eventName])) {
            return [];
        }

        $allListeners = [];
        /** @var array<callable> $listener */
        foreach ($this->listeners[$eventName] as $listener) {
            $allListeners[] = $listener;
        }

        return array_merge(...$allListeners);
    }

    public function addSubscriber(object|string $subscriber): void
    {
        if (! method_exists($subscriber, 'getSubscribedEvents')) {
            return;
        }

        $events = $subscriber::getSubscribedEvents();

        $subscriberIsString = is_object($subscriber);

        foreach ($events as $eventName => $method) {
            if (is_string($method) && method_exists($subscriber, $method)) {
                $callable = [$subscriber, $method];
                if ($subscriberIsString) {
                    $callable = $subscriber . '@' . $method;
                }

                $this->addListener($eventName, $callable); // @phpstan-ignore-line

                continue;
            }

            if (! is_array($method)) {
                continue;
            }

            $listeners = $method;
            /** @var array<array<string|int>> $listeners */
            foreach ($listeners as [$method, $priority]) { // @phpstan-ignore-line
                /** @var callable $callable */
                $callable = [$subscriber, $method];
                if ($subscriberIsString) {
                    /** @var string $callable */
                    $callable = $subscriber . '@' . $method;
                }

                $this->addListener($eventName, $callable, (int) ($priority ?? 0));
            }
        }
    }

    // --------------------------------------------------

    public function dispatch(object|string $event, mixed $data = null): void
    {
        $eventIsObject = is_object($event);
        $eventName = $eventIsObject ? get_class($event) : $event;

        if (! isset($this->listeners[$eventName])) {
            return;
        }

        $isStoppable = $eventIsObject && method_exists($event, 'isPropagationStopped');
        krsort($this->listeners[$eventName]); // sort by priority, the highest number first

        foreach ($this->listeners[$eventName] as $listeners) {
            foreach ($listeners as $listener) {
                if (is_string($listener) && str_contains($listener, '@')) {
                    [$fqcn, $method] = explode('@', $listener, 2);
                    $listener = [$this->container->get($fqcn), $method];
                }

                $stopPropagation = $listener($event, $data); // @phpstan-ignore-line

                // @phpstan-ignore-next-line
                $stopPropagation = $stopPropagation || ($isStoppable && $event->isPropagationStopped());
                if ($stopPropagation) {
                    return;
                }
            }
        }
    }
}
