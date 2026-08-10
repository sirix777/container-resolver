# Container Resolver

Strict PSR-11 container service resolver and typed configuration reader for reusable PHP packages.

## Stability and requirements

`1.0.0` is the first stable release. It supports PHP `^8.2` and `psr/container` 1.x or 2.x. Public contracts follow Semantic Versioning; see [UPGRADE.md](UPGRADE.md) when moving from 0.1.x.

## Installation

```bash
composer require sirix/container-resolver
```

## Why

Package factories often need to read services and configuration from framework containers. Manual checks tend to repeat the same code, silently coerce invalid values, or fail with messages that do not explain which factory needs which value.

`ContainerResolver` and `ConfigReader` keep factory code small while preserving explicit failures:

- missing container services throw package-level not-found exceptions;
- invalid service types throw package-level container exceptions;
- missing required config values throw package-level config exceptions;
- existing invalid config values always throw;
- optional missing config values can use defaults;
- scalar config values are not coerced.

The package is framework-agnostic and only requires PHP and `psr/container` at runtime.

## Container services

Use `ContainerResolver` when a service id is the expected class or interface:

```php
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ContainerResolver;

final class AuthManagerFactory
{
    public function __invoke(ContainerInterface $container): AuthManagerInterface
    {
        $resolver = ContainerResolver::forFactory($container, self::class);

        return new AuthenticationManager(
            $resolver->get(TokenStorageProviderInterface::class),
            $resolver->get(TokenTransportInterface::class),
        );
    }
}
```

Use `getAs()` when the service id is a custom string but the expected type is known:

```php
$storage = $resolver->getAs('app.storage.redis', TokenStorageInterface::class);
```

Use `getExisting()` when you want the raw service value and will validate it yourself:

```php
$value = $resolver->getExisting('config');
```

`optional()` is the typed optional counterpart to `get()`: the service id must be a class or interface name. It returns `null` only when the service is absent; a present service with the wrong type throws `InvalidContainerServiceException`.

```php
$logger = $resolver->optional(LoggerInterface::class);
```

Use `optionalAs()` when the service id is custom but its expected type is known, and `optionalExisting()` only when intentionally handling an untyped raw service yourself:

```php
$cache = $resolver->optionalAs('app.cache', CacheInterface::class);
$value = $resolver->optionalExisting('app.feature-flags', default: []);
```

`optionalArray()` accepts any PHP array, including lists. `optionalMap()` is stricter: it accepts only arrays whose keys are strings, which makes it appropriate for the root `config` service. Both return `[]` for a missing service and throw when a present service violates their contract.

```php
$items = $resolver->optionalArray('app.middleware');
$config = $resolver->optionalMap('config');
```

`ConfigReader::fromContainer()` reads its root configuration through `optionalMap()`, so list-shaped root configuration is rejected.

## Typed configuration

`ConfigReader` reads nested arrays with dot paths:

```php
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;

$resolver = ContainerResolver::forFactory($container, self::class);
$config = ConfigReader::fromContainer($resolver);

$driver = $config->stringEnum(
    'authentication.transport.driver',
    ['bearer', 'cookie'],
    default: 'bearer',
);
```

Available methods:

```php
$config->has('app.name');
$config->get('app.name', default: 'demo');
$config->required('app.name');

$config->string('app.name', default: 'demo');
$config->requiredString('app.name');
$config->nonEmptyString('app.name', default: 'demo');
$config->requiredNonEmptyString('app.name');
$config->optionalString('app.name');
$config->optionalNonEmptyString('app.name');

$config->bool('debug', default: false);
$config->requiredBool('debug');

$config->int('port', default: 8080);
$config->requiredInt('port');

$config->array('items', default: []);
$config->requiredArray('items');

$config->list('entities', default: []);
$config->requiredList('entities');
$config->stringList('entities', default: []);
$config->requiredStringList('entities');
$config->nonEmptyStringList('entities', default: []);
$config->requiredNonEmptyStringList('entities');

$config->map('storages', default: []);
$config->requiredMap('storages');

$config->enum('log.level', LogLevel::class, default: LogLevel::Info);
$config->requiredEnum('log.level', LogLevel::class);

$config->stringEnum('driver', ['bearer', 'cookie'], default: 'bearer');
$config->requiredStringEnum('driver', ['bearer', 'cookie']);
```

## Strictness rules

Missing optional values return the supplied default, after the default has been validated for the reader's declared contract:

```php
$config->string('app.name', default: 'demo');
```

Existing invalid values always throw `InvalidConfigValueException`:

```php
['app' => ['name' => 123]]; // invalid for string()
['debug' => 'false'];       // invalid for bool()
['port' => '8080'];         // invalid for int()
```

Valid scalar values must already have the expected type:

```php
['app' => ['name' => 'api']];
['debug' => false];
['port' => 8080];
```

String readers trim leading and trailing whitespace from configured string values:

```php
['app' => ['name' => ' api ']]; // returned as 'api'
```

This applies to configured string values, string lists, string enums, and PHP enum case names/string-backed values. String defaults are normalized and validated by the same reader contract, so a blank default is not accepted where a non-empty string is required. Trimming is normalization of strings only; scalar coercion is never performed.

### Paths and `null`

Paths use dot-separated segments such as `app.name`. A path cannot be empty and cannot contain an empty segment (`app..name`). Dots are not escapable, so a configuration key containing `.` cannot be addressed as one key. Numeric list indexes can be addressed as segments (`servers.0.host`).

`null` is an existing value, not a missing path. `has('app.name')` is therefore `true` for `['app' => ['name' => null]]`, and `get()` returns `null`; typed readers reject it with `InvalidConfigValueException`. A `null` at an intermediate path makes a deeper path absent.

`optionalNonEmptyString()` is useful for optional values where an empty string should be treated as not configured:

```php
$config->optionalNonEmptyString('cookie.domain');
```

Behavior:

```php
[];                              // null
['cookie' => ['domain' => '']];  // null
['cookie' => ['domain' => '   ']]; // null
['cookie' => ['domain' => ' example.com ']]; // 'example.com'
['cookie' => ['domain' => 123]]; // invalid
```

`list()` requires a sequential list array:

```php
$config = ConfigReader::fromArray([
    'entities' => [
        'src/App/src/Entity',
    ],
]);

$entities = $config->nonEmptyStringList('entities', default: []);
```

`entities` may be absent; in that case the default `[]` is returned. If `entities` exists, it must be a list of non-empty strings.

`enum()` returns real PHP enum instances. Its `default:` must be an instance of the requested enum class:

```php
enum LogLevel: int
{
    case Debug = 100;
    case Info = 200;
    case Warning = 300;
}

$level = $config->enum(
    'logging.level',
    LogLevel::class,
    default: LogLevel::Info,
);
```

Accepted configured values:

```php
['logging' => ['level' => LogLevel::Debug]]; // enum instance
['logging' => ['level' => 'Debug']];         // case name
['logging' => ['level' => ' debug ']];       // trimmed case name, case-insensitive
['logging' => ['level' => 100]];             // backed value for int-backed enum
```

For backed enums, a matching backed value is resolved before a case name. Case names are trimmed and matched case-insensitively. String-backed values are trimmed; int-backed values must be integers.

Numeric strings are not coerced for int-backed enums:

```php
['logging' => ['level' => '100']]; // invalid for LogLevel: int
```

For string-backed enums, the string backed value is accepted:

```php
enum Driver: string
{
    case Bearer = 'bearer';
    case Cookie = 'cookie';
}

$driver = $config->requiredEnum('driver', Driver::class);
```

`map()` requires all keys to be strings:

```php
$config = ConfigReader::fromArray([
    'storages' => [
        'redis' => 'app.storage.redis',
        'db' => 'app.storage.db',
    ],
]);

$storages = $config->map('storages', default: []);
```

Lists are rejected by `map()` because they do not describe named configuration entries. String-keyed maps are rejected by `list()` because they are not sequential lists.

## Long-running process safety

The package is safe to use in long-running processes such as RoadRunner, Swoole, ReactPHP, queue workers, and persistent Mezzio/Laminas applications.

It does not use:

- global mutable state;
- static runtime caches;
- request-specific static properties;
- singleton resolver instances.

Resolver and reader instances are cheap to create per factory invocation:

```php
public function __invoke(ContainerInterface $container): SomeService
{
    $resolver = ContainerResolver::forFactory($container, self::class);
    $config = ConfigReader::fromContainer($resolver);

    return new SomeService(
        dependency: $resolver->get(DependencyInterface::class),
        enabled: $config->bool('some.enabled', default: true),
    );
}
```

Exceptions do not retain service instances or full config arrays.

## Exceptions

All package exceptions implement:

```php
Sirix\ContainerResolver\Exception\ResolverException
```

Container-related exceptions also implement:

```php
Sirix\ContainerResolver\Exception\ContainerResolverException
```

Config-related exceptions also implement:

```php
Sirix\ContainerResolver\Exception\ConfigReaderException
```

Concrete exceptions:

```php
Sirix\ContainerResolver\Exception\MissingContainerServiceException
Sirix\ContainerResolver\Exception\InvalidContainerServiceException
Sirix\ContainerResolver\Exception\MissingConfigValueException
Sirix\ContainerResolver\Exception\InvalidConfigValueException
```

`MissingContainerServiceException` implements `Psr\Container\NotFoundExceptionInterface`.
`InvalidContainerServiceException` implements `Psr\Container\ContainerExceptionInterface`.

`ContainerResolver` wraps missing-service and invalid-type failures into package exceptions. Other container resolution failures from the underlying PSR-11 container are propagated unchanged.

Diagnostic messages identify configuration paths, expected contracts, and actual types, but never include raw configured values. This keeps error reporting safe for configuration that may contain credentials or other secrets.

That means:

- missing service or `NotFoundExceptionInterface` from the container becomes `MissingContainerServiceException`;
- wrong resolved service type becomes `InvalidContainerServiceException`;
- other `ContainerExceptionInterface` failures from the container remain the original container exception.

Public methods document their expected failure modes with `@throws` annotations.

Consumers can catch and wrap package exceptions:

```php
use Sirix\ContainerResolver\Exception\ResolverException;

try {
    // factory logic
} catch (ResolverException $exception) {
    throw MyPackageConfigurationException::fromPrevious($exception);
}
```

## Examples

Factory with configured service ids:

```php
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;

final class TokenStorageProviderFactory
{
    public function __invoke(ContainerInterface $container): TokenStorageProviderInterface
    {
        $resolver = ContainerResolver::forFactory($container, self::class);
        $config = ConfigReader::fromContainer($resolver);

        $defaultStorage = $config->nonEmptyString('authentication.default_storage', default: 'null');

        $storages = [
            'null' => $resolver->get(NullTokenStorage::class),
        ];

        foreach ($config->map('authentication.storages', default: []) as $name => $serviceId) {
            if (! is_string($serviceId) || '' === $serviceId) {
                throw InvalidConfigValueException::forType(
                    "authentication.storages.{$name}",
                    'non-empty-string',
                    $serviceId,
                    self::class,
                );
            }

            $storages[$name] = $resolver->getAs($serviceId, TokenStorageInterface::class);
        }

        if (! isset($storages[$defaultStorage])) {
            throw InvalidConfigValueException::forAllowedValues(
                'authentication.default_storage',
                array_keys($storages),
                $defaultStorage,
                self::class,
            );
        }

        return new TokenStorageProvider($defaultStorage, $storages);
    }
}
```

## Design notes

This package intentionally does not provide:

- a DI container;
- autowiring;
- service definitions;
- framework adapters;
- config schema compilation;
- deep config merging;
- environment variable processors;
- secret resolution;
- runtime caching;
- scalar coercion.
