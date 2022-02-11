# Events

`\FlorentPoujol\Smol\Events\EventDispatcher` is a PSR-14 event dispatcher.

## Dispatching events

An event can be
- a string, with optional data
- an object, where the fully qualified class name is considered as the event's name

To dispatch an event, call the `dispatch(object|string $event, mixed $data = null): void` method on the dispatcher instance.

```php
$dispatcher->dispatch('my.event');
$dispatcher->dispatch('my.event', $withData);

$event = new SomethingHappened();
$dispatcher->dispatch($event);
```

the listeners receive exactly what is passed to the dispatch() method, so the event name and its data, or just the event object instance.

**Stopping the propagation**

A listener can prevent more listeners to be called, either by returning `true`, or by implementing the `\Psr\EventDispatcher\StoppableEventInterface` interface and have the `isPropagationStopped(): bool` method return true.

## Registering listeners

Any callable can be a listener.

Register a listener with the `addListener(string $eventName, callable $listener, int $priority = 0): void` method.

Listeners with a high priority wil be called first.

### Subscribers

A subscriber is a class that listen for one or several events and that advertise them via the `getSubscribedEvents(): array` method.

The method returns an array with the event names as key and the value:
- either the class method name that handle this event
- an array of method names and priority as array, to register several methods for the same even and a different priority
