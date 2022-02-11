# Rate Limiter

A rate limiter will prevent something to execute an action too many times over a certains period of time.

It is most typically used to prevent access to routes like login, or the ones that have side effect like sending emails.

## Fixed vs Sliding window

A rate limiter is setup with a maximum number of hits and a window of time. That window can be **fixed** or **sliding**.

A fixed window **begins at the first hit** and once the end of the window is reached a new one can open.

A sliding window **ends at the current hit** (inclusive) and takes into accounts all hits from the window size **in the past** up to now.

## Setup

To get a rate limiter instance, typehint an argument against `\FlorentPoujol\Smol\RateLimiter\CacheRateLimiter`, then setup it, either from values coming from the config with the `setupFromConfig(string $configKey): void` method, or directly with the `setup(string $name, int $maxHits, int $windowSizeInSeconds, bool $windowIsSliding = false): void` method.

Examples:
```php
$limiter->setup(name: 'my-limiter', maxHits: 100, windowSizeInSeconds: 60);
// or
$limiter->setupFromConfig('my-limiter');
```

And the config in the `app.php` file, each rate-limiter name must match a key in the the `rate_limiters` array :
```php
return [
    'rate_limiters' => [
        'my-limiter' => [
            'maxHits' => 100,
            'windowSizeInSeconds' => 60,
            // 'windowIsSliding' => false, // default is false, key is optional         
        ],
        // other rate limiters as needed
    ],
];
```

## Usage

The main usage is to call the `hitIsAllowed(): bool` method on the limiter that returns if yes or no the action is allowed.

When the action is not allowed, you can call the `remainingTimeInSeconds(): int` method to get the time the user needs to wait before the action can be performed again.  

```php
if ($limiter->hitIsAllowed()) {
    // do stuff
} else {
    $waitTime = $limiter->remainingTimeInSeconds();
}
```

You can also call the `remainingHitsInWindow(): int` method to get that information.

