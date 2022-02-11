# Lock

A lock is a mechanism that typically prevent two systems/process/requests to access the same resource at the same time. 

The first system **acquire** a lock if it doesn't exist yet, which concretely means writing a value somewhere.
While that value exists, other systems can not get the lock and must wait to it to be **released**.

Once the first system has finished doing what it wanted, it releases the lock, which deletes the value.
Or the lock is automatically released after some time to prevent a **dead lock**, when a lock is still acquired but the system that acquired it can't release it because there was an exception for instance.  

## CacheLock

The CacheLock service writes the lock in the default cache service.

To create a lock, you typically would typehint againts the `\FlorentPoujol\SmolFramework\Lock\CacheLockFactory` then call the `make(string $name, int $ttlInSeconds): CacheLock` on it.  
Or you can typehint for any cache backend and instantiate directly the service : `new CacheLock(string $name, int $ttlInSeconds, CacheInterface $cache)`

The name is the unique value that identifies the lock.    
The second argument is the maximum duration the lock can exist before it is automatically released.

Attempt to acquire the lock with the `acquire(): bool` method that return true is the lock was successfully acquired, false otherwise.  
Release it as soon as possible with the `release(): void` method.

```php
$lock = $factory->make('lock_name', 10);
// or 
$lock = new CacheLock('lock_name', 10, $cache);

if ($lock->aquire()) {
    // do stuff 
    
    $lock->release();    
} else {
    // lock not acquired
}
```

If the lock isn't acquired, you can wait in a loop until it is the case :

```php
$maxTime = time() + 5; 
do {
    if ($lock->aquire()) {
        // do stuff

        $lock->release();

        break;    
    }

    usleep(250_000);
} while ($maxTime > time());
```

Or you can call the `wait(int $maxWaitTimeInSeconds, callable $callback, int $loopWaitTimeInMilliseconds = 100): mixed` method which will automatically wait in a loop until the lock is acquired or until the `maxWaitTimeInSeconds` is elapsed.
Buy default each loop wait 100 milliseconds before attempting again to acquire the lock, it can be changed with the third argument.

When the callback has finished running, the lock will automatically be released.  
The method returns whatever the callback has returned, or `null`.

```php
$lock->wait(5, function (): void {
    // do stuff
})
```
