# Identifiers

The identifier component allows to easily generate (universally) unique identifier values in an object-oriented way.

## Available identifiers

### UUID v4

You can create a standard UUID v4 like so:
```php
$uuid = UUIDv4::make();

$uuid->getUuid(); // ie: 9ab8188a-2384-46c2-9b9a-aed9aefead41
$uuid->getHex(); // ie: 9ab8188a238446c29b9aaed9aefead41
```

You can also create a UUID objects from a pre-existing string instead of generating a new one:
```php
$uuid = UUIDv4::fromString('9ab8188a-2384-46c2-9b9a-aed9aefead41');
$uuid = UUIDv4::fromString('9ab8188a238446c29b9aaed9aefead41');
```

### TimeBased8

The TimeBased8 identifier generate an always positive, incrementing integer, composed of the micro-timestamp on the 7 first bytes and of 1 trailing random byte.    

```php
$uuid = TimeBased8::make();

$uuid->getUuid(); // UnexpectedValueException because the value isn't 16 bytes
$uuid->getHex(); // ie: 05d8e8e55fb78ef2
$uuid->getInteger(); // ie: 421342637010161394
```

You can also create an objects from a number:
```php
$uuid = TimeBased8::fromHex('05d8e8e55fb78ef2');
$uuid = TimeBased8::fromInteger(421342637010161394);
```

As of February 2022 the value is still small enough to be stored on a signed 8 bytes integer.

Unlike UUID v1, since the time component is at the beginning, they are indexing friendly and so can be good candidate for database primary keys.

### TimeBased16

Same as the TimeBase8, but that trails with 9 random bytes instead of 1, for more uniqueness.

```php
$uuid = TimeBased16::make();

$uuid->getUuid(); // ie: 05d8e8ef-ae53-daa0-dde8-fbce18cc5639
$uuid->getHex(); // ie: 05d8e8efae53daa0dde8fbce18cc5639
```

## Create you own

To create your own identifier class:
- implements the `IdentifierInterface` interface
- or extends the abstract `Identifier` class and implement the `generate(): string` method that must return the identifier as a binary string.
