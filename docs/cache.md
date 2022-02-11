# Cache

Caching is the act of storing a value, typically temporarily, that was "expensive" to get in a store that is very fast to use.

The framework provide the simple and classic `\FlorentPoujol\Smol\Cache\CacheInterface` interface :
```php
interface CacheInterface
{
    public function set(string $key, mixed $value, int $ttlInSeconds = null): void;

    public function has(string $key): bool;

    /**
     * @return array<string>
     */
    public function keys(string $prefix = ''): array;

    public function get(string $key, mixed $default = null): mixed;

    public function delete(string $key): void;

    /**
     * @return int The number of deleted entries
     */
    public function flush(string $prefix = ''): int;
}
```

Example:
```php
final class Controller
{
    public function __construct(
        private \FlorentPoujol\Smol\Cache\CacheInterface $cache
        private \FlorentPoujol\Smol\Database\QueryBuilder $queryBuilder
    ) {
    }

    public function getStuff(): Response
    {
        $data = $this->cache->get('my-cache-key');
        if ($data === null) {
            $data = $this->queryBuilder->fromTable('my-table')->get();
            
            $this->cache->set('my-cache-key', $data, 3600 * 24); // TTL = 1 day
        }
        
        return new Response(body: $data);    
    }
}
```

Smol has three built-in implementations/stores for that interface :
- Redis
- In-memory
- Database

The cache is used by rate limiters and locks.

## PHPRedis

You can use the classic [Redis](https://redis.io) as your cache backend, via the [PHPRedis extension](https://github.com/phpredis/phpredis).

In the configuration `app.php` file, define a `phpredis` key with the arguments for the [`connect()`](https://github.com/phpredis/phpredis#connect-open) method.

Exemple :
```php
'phpredis' => [
    // each key must match the name of an argument of the PHPRedis::connect() method
    // see https://github.com/phpredis/phpredis#connect-open
    
    'host' => env('REDIS_HOST'),
    // optional
    'port' => env('REDIS_PORT'),
    'timeout' => env('REDIS_TIMEOUT'),
    'auth' => [
        'user' => env('REDIS_USERNAME'),
        'pass' => env('REDIS_PASSWORD'),    
    ],
    // ...
],
```

Then bind the `\FlorentPoujol\Smol\Cache\RedisCache` to the `\FlorentPoujol\Smol\Cache\CacheInterface` during the boot of the framework.

```php
$container->bind(\FlorentPoujol\Smol\Cache\CacheInterface::class, \FlorentPoujol\Smol\Cache\RedisCache::class);
```

## In-memory

The `\FlorentPoujol\Smol\Cache\CacheInterface` is by default binded to the `\FlorentPoujol\Smol\Cache\InMemoryCache` implementation, which stores the value in a simple array and never has to communicate to any backend/store or even file.

Expired values are not taken into account by the various methods like `get()` or `keys()`, but unlike with Redis, they will not be deleted automatically.

So in addition to the methods of the interface, it's possible to call the `flushExpiredValues(): int` method to do just that. It returns the number or flushed values.  

It is good practice to call this method regularly via a task.

Do note that the in-memory cache has no persistent storage, so everything in it is lost if the server is restarted.


## Database cache

Alternatively you can also use any PDO-compatible SQL database as the cache backend.

The table must look like this (here for MySQL) :
```SQL
create table smol_cache
(
    `key` varchar(100) null,
    value text null,
    expire_at timestamp null,
    constraint smol_cache_pk primary key (`key`)
);

create index smol_cache_key_expire_at_index on smol_cache (`key`, expire_at);
create index smol_cache_expire_at_index on smol_cache (expire_at);
```

Like for the in-memory cache, you shall call the `flushExpiredValues(): int` method regularly.

The Database cache may be the least pertinent option between Redis and in-memory, but can be useful on systems where you can't install Redis and you need persistent storage for instance.
