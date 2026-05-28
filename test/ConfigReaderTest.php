<?php

declare(strict_types=1);

namespace SirixTest\ContainerResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\ContainerResolver\Exception\MissingConfigValueException;
use SirixTest\ContainerResolver\TestAsset\ArrayContainer;
use SirixTest\ContainerResolver\TestAsset\LogLevel;
use SirixTest\ContainerResolver\TestAsset\StringDriver;
use SirixTest\ContainerResolver\TestAsset\UnitMode;

#[CoversClass(ConfigReader::class)]
#[CoversClass(ContainerResolver::class)]
#[CoversClass(InvalidConfigValueException::class)]
#[CoversClass(MissingConfigValueException::class)]
final class ConfigReaderTest extends TestCase
{
    public function testFromContainerReadsConfigServiceAndPreservesContext(): void
    {
        $resolver = ContainerResolver::forContext(new ArrayContainer([
            'config' => [
                'app' => [
                    'name' => 'demo',
                ],
            ],
        ]), 'factory');

        $config = ConfigReader::fromContainer($resolver);

        self::assertSame('demo', $config->requiredString('app.name'));

        try {
            $config->requiredString('app.missing');
            self::fail('Expected missing config exception.');
        } catch (MissingConfigValueException $exception) {
            self::assertSame(
                'Configuration value "app.missing" is required by factory but is not defined.',
                $exception->getMessage(),
            );
        }
    }

    public function testFromContainerUsesEmptyConfigWhenConfigServiceIsMissing(): void
    {
        $config = ConfigReader::fromContainer(new ContainerResolver(new ArrayContainer()));

        self::assertFalse($config->has('app.name'));
    }

    public function testHasReturnsTrueForExistingPathEvenWhenValueIsNull(): void
    {
        $config = ConfigReader::fromArray([
            'app' => [
                'name' => null,
            ],
        ]);

        self::assertTrue($config->has('app.name'));
    }

    public function testHasReturnsFalseForMissingPath(): void
    {
        $config = ConfigReader::fromArray([
            'app' => [],
        ]);

        self::assertFalse($config->has('app.name'));
    }

    public function testGetReturnsConfiguredValue(): void
    {
        $config = ConfigReader::fromArray([
            'app' => [
                'name' => 'demo',
            ],
        ]);

        self::assertSame('demo', $config->get('app.name'));
    }

    public function testGetReturnsDefaultForMissingValue(): void
    {
        $config = ConfigReader::fromArray([]);

        self::assertSame('demo', $config->get('app.name', 'demo'));
    }

    public function testRequiredThrowsMissingExceptionForMissingPath(): void
    {
        $config = ConfigReader::fromArray([], 'factory');

        $this->expectException(MissingConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "app.name" is required by factory but is not defined.');

        $config->required('app.name');
    }

    public function testStringReturnsDefaultWhenMissing(): void
    {
        $config = ConfigReader::fromArray([]);

        self::assertSame('demo', $config->string('app.name', 'demo'));
    }

    public function testStringReturnsConfiguredString(): void
    {
        $config = ConfigReader::fromArray(['app' => ['name' => 'api']]);

        self::assertSame('api', $config->string('app.name', 'demo'));
    }

    public function testStringRejectsNonStringExistingValue(): void
    {
        $config = ConfigReader::fromArray(['app' => ['name' => 123]]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "app.name" must be string; int given.');

        $config->string('app.name', 'demo');
    }

    public function testRequiredStringThrowsWhenMissing(): void
    {
        $config = ConfigReader::fromArray([]);

        $this->expectException(MissingConfigValueException::class);

        $config->requiredString('app.name');
    }

    public function testRequiredStringRejectsNonStringExistingValue(): void
    {
        $config = ConfigReader::fromArray(['app' => ['name' => false]]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "app.name" must be string; bool given.');

        $config->requiredString('app.name');
    }

    public function testNonEmptyStringRejectsEmptyString(): void
    {
        $config = ConfigReader::fromArray(['app' => ['name' => '']]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "app.name" must be non-empty-string; string given.');

        $config->nonEmptyString('app.name', 'demo');
    }

    public function testRequiredNonEmptyStringRejectsMissingAndEmptyValues(): void
    {
        $missing = ConfigReader::fromArray([]);

        try {
            $missing->requiredNonEmptyString('app.name');
            self::fail('Expected missing config exception.');
        } catch (MissingConfigValueException) {
            self::addToAssertionCount(1);
        }

        $empty = ConfigReader::fromArray(['app' => ['name' => '']]);

        $this->expectException(InvalidConfigValueException::class);

        $empty->requiredNonEmptyString('app.name');
    }

    public function testOptionalStringReturnsNullWhenMissing(): void
    {
        $config = ConfigReader::fromArray([]);

        self::assertNull($config->optionalString('app.name'));
    }

    public function testOptionalStringReturnsStringWhenPresent(): void
    {
        $config = ConfigReader::fromArray(['app' => ['name' => 'demo']]);

        self::assertSame('demo', $config->optionalString('app.name'));
    }

    public function testOptionalStringRejectsNonStringWhenPresent(): void
    {
        $config = ConfigReader::fromArray(['app' => ['name' => null]]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "app.name" must be string; null given.');

        $config->optionalString('app.name');
    }

    public function testBoolAcceptsOnlyBool(): void
    {
        $config = ConfigReader::fromArray(['debug' => false]);

        self::assertFalse($config->bool('debug', true));
        self::assertTrue($config->bool('missing', true));
    }

    public function testBoolRejectsStringBooleans(): void
    {
        $config = ConfigReader::fromArray(['debug' => 'false']);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "debug" must be bool; string given.');

        $config->bool('debug', false);
    }

    public function testRequiredBoolRejectsMissingAndInvalidValues(): void
    {
        $missing = ConfigReader::fromArray([]);

        try {
            $missing->requiredBool('debug');
            self::fail('Expected missing config exception.');
        } catch (MissingConfigValueException) {
            self::addToAssertionCount(1);
        }

        $invalid = ConfigReader::fromArray(['debug' => 1]);

        $this->expectException(InvalidConfigValueException::class);

        $invalid->requiredBool('debug');
    }

    public function testIntAcceptsOnlyInt(): void
    {
        $config = ConfigReader::fromArray(['port' => 8080]);

        self::assertSame(8080, $config->int('port', 80));
        self::assertSame(80, $config->int('missing', 80));
    }

    public function testIntRejectsNumericStrings(): void
    {
        $config = ConfigReader::fromArray(['port' => '8080']);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "port" must be int; string given.');

        $config->int('port', 80);
    }

    public function testRequiredIntRejectsMissingAndInvalidValues(): void
    {
        $missing = ConfigReader::fromArray([]);

        try {
            $missing->requiredInt('port');
            self::fail('Expected missing config exception.');
        } catch (MissingConfigValueException) {
            self::addToAssertionCount(1);
        }

        $invalid = ConfigReader::fromArray(['port' => '8080']);

        $this->expectException(InvalidConfigValueException::class);

        $invalid->requiredInt('port');
    }

    public function testArrayAcceptsArraysAndReturnsDefaultWhenMissing(): void
    {
        $config = ConfigReader::fromArray(['items' => ['a', 'b']]);

        self::assertSame(['a', 'b'], $config->array('items', []));
        self::assertSame(['fallback'], $config->array('missing', ['fallback']));
    }

    public function testArrayRejectsScalarValues(): void
    {
        $config = ConfigReader::fromArray(['items' => 'invalid']);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "items" must be array; string given.');

        $config->array('items', []);
    }

    public function testRequiredArrayRejectsMissingValues(): void
    {
        $config = ConfigReader::fromArray([]);

        $this->expectException(MissingConfigValueException::class);

        $config->requiredArray('items');
    }

    public function testListAcceptsListsAndReturnsDefaultWhenMissing(): void
    {
        $config = ConfigReader::fromArray(['entities' => ['src/App/src/Entity']]);

        self::assertSame(['src/App/src/Entity'], $config->list('entities', []));
        self::assertSame(['fallback'], $config->list('missing', ['fallback']));
    }

    public function testListRejectsMaps(): void
    {
        $config = ConfigReader::fromArray(['entities' => ['app' => 'src/App/src/Entity']]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "entities" must be list<mixed>; array given.');

        $config->list('entities', []);
    }

    public function testListRejectsScalarValues(): void
    {
        $config = ConfigReader::fromArray(['entities' => 'src/App/src/Entity']);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "entities" must be array; string given.');

        $config->list('entities', []);
    }

    public function testRequiredListRejectsMissingValues(): void
    {
        $config = ConfigReader::fromArray([]);

        $this->expectException(MissingConfigValueException::class);

        $config->requiredList('entities');
    }

    public function testStringListAcceptsStringListsAndReturnsDefaultWhenMissing(): void
    {
        $config = ConfigReader::fromArray(['entities' => ['src/App/src/Entity']]);

        self::assertSame(['src/App/src/Entity'], $config->stringList('entities', []));
        self::assertSame(['fallback'], $config->stringList('missing', ['fallback']));
    }

    public function testStringListRejectsNonStringItems(): void
    {
        $config = ConfigReader::fromArray(['entities' => ['src/App/src/Entity', 123]]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "entities.1" must be string; int given.');

        $config->stringList('entities', []);
    }

    public function testRequiredStringListRejectsMissingValues(): void
    {
        $config = ConfigReader::fromArray([]);

        $this->expectException(MissingConfigValueException::class);

        $config->requiredStringList('entities');
    }

    public function testNonEmptyStringListAcceptsNonEmptyStringListsAndReturnsDefaultWhenMissing(): void
    {
        $config = ConfigReader::fromArray(['entities' => ['src/App/src/Entity']]);

        self::assertSame(['src/App/src/Entity'], $config->nonEmptyStringList('entities', []));
        self::assertSame(['fallback'], $config->nonEmptyStringList('missing', ['fallback']));
    }

    public function testNonEmptyStringListRejectsEmptyStringItems(): void
    {
        $config = ConfigReader::fromArray(['entities' => ['']]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "entities.0" must be non-empty-string; string given.');

        $config->nonEmptyStringList('entities', []);
    }

    public function testRequiredNonEmptyStringListRejectsMissingValues(): void
    {
        $config = ConfigReader::fromArray([]);

        $this->expectException(MissingConfigValueException::class);

        $config->requiredNonEmptyStringList('entities');
    }

    public function testMapAcceptsStringKeyedArrays(): void
    {
        $config = ConfigReader::fromArray([
            'storages' => [
                'redis' => 'app.storage.redis',
                'db' => 'app.storage.db',
            ],
        ]);

        self::assertSame([
            'redis' => 'app.storage.redis',
            'db' => 'app.storage.db',
        ], $config->map('storages', []));
        self::assertSame(['default' => true], $config->map('missing', ['default' => true]));
    }

    public function testMapRejectsLists(): void
    {
        $config = ConfigReader::fromArray([
            'storages' => ['app.storage.redis'],
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "storages" must be map<string, mixed>; array given.');

        $config->map('storages', []);
    }

    public function testRequiredMapRejectsMissingValues(): void
    {
        $config = ConfigReader::fromArray([]);

        $this->expectException(MissingConfigValueException::class);

        $config->requiredMap('storages');
    }

    public function testEnumReturnsDefaultWhenMissing(): void
    {
        $config = ConfigReader::fromArray([]);

        self::assertSame(LogLevel::Info, $config->enum('log.level', LogLevel::class, LogLevel::Info));
    }

    public function testEnumAcceptsExistingEnumInstances(): void
    {
        $config = ConfigReader::fromArray(['log' => ['level' => LogLevel::Warning]]);

        self::assertSame(LogLevel::Warning, $config->enum('log.level', LogLevel::class, LogLevel::Info));
    }

    public function testEnumResolvesCaseNameCaseInsensitively(): void
    {
        $config = ConfigReader::fromArray(['log' => ['level' => 'debug']]);

        self::assertSame(LogLevel::Debug, $config->enum('log.level', LogLevel::class, LogLevel::Info));
    }

    public function testEnumResolvesBackedEnumValueStrictly(): void
    {
        $config = ConfigReader::fromArray([
            'driver' => 'cookie',
            'log' => ['level' => 100],
        ]);

        self::assertSame(StringDriver::Cookie, $config->requiredEnum('driver', StringDriver::class));
        self::assertSame(LogLevel::Debug, $config->requiredEnum('log.level', LogLevel::class));
    }

    public function testEnumDoesNotCoerceNumericStringsForIntBackedEnums(): void
    {
        $config = ConfigReader::fromArray(['log' => ['level' => '100']]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "log.level" must be one of "Debug", "Info", "Warning"; "100" given.');

        $config->enum('log.level', LogLevel::class, LogLevel::Info);
    }

    public function testEnumSupportsUnitEnumsByCaseName(): void
    {
        $config = ConfigReader::fromArray(['mode' => 'Async']);

        self::assertSame(UnitMode::Async, $config->requiredEnum('mode', UnitMode::class));
    }

    public function testRequiredEnumRejectsMissingValues(): void
    {
        $config = ConfigReader::fromArray([]);

        $this->expectException(MissingConfigValueException::class);

        $config->requiredEnum('log.level', LogLevel::class);
    }

    public function testRequiredEnumRejectsUnsupportedValues(): void
    {
        $config = ConfigReader::fromArray(['log' => ['level' => 'verbose']]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "log.level" must be one of "Debug", "Info", "Warning"; "verbose" given.');

        $config->requiredEnum('log.level', LogLevel::class);
    }

    public function testStringEnumAcceptsAllowedValues(): void
    {
        $config = ConfigReader::fromArray(['driver' => 'bearer']);

        self::assertSame('bearer', $config->stringEnum('driver', ['bearer', 'cookie'], 'cookie'));
        self::assertSame('cookie', $config->stringEnum('missing', ['bearer', 'cookie'], 'cookie'));
    }

    public function testStringEnumRejectsUnsupportedValues(): void
    {
        $config = ConfigReader::fromArray(['driver' => 'redis']);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "driver" must be one of "bearer", "cookie"; "redis" given.');

        $config->stringEnum('driver', ['bearer', 'cookie'], 'bearer');
    }

    public function testRequiredStringEnumRejectsMissingUnsupportedAndNonStringValues(): void
    {
        $missing = ConfigReader::fromArray([]);

        try {
            $missing->requiredStringEnum('driver', ['bearer', 'cookie']);
            self::fail('Expected missing config exception.');
        } catch (MissingConfigValueException) {
            self::addToAssertionCount(1);
        }

        $invalid = ConfigReader::fromArray(['driver' => false]);

        $this->expectException(InvalidConfigValueException::class);

        $invalid->requiredStringEnum('driver', ['bearer', 'cookie']);
    }

    public function testMultipleReadersDoNotLeakValuesBetweenInstances(): void
    {
        $first = ConfigReader::fromArray(['app' => ['name' => 'first']]);
        $second = ConfigReader::fromArray(['app' => ['name' => 'second']]);

        self::assertSame('first', $first->requiredString('app.name'));
        self::assertSame('second', $second->requiredString('app.name'));
    }

    public function testExceptionMessagesDoNotDumpFullConfigArrays(): void
    {
        $config = ConfigReader::fromArray([
            'app' => [
                'secret' => 'token',
            ],
        ]);

        try {
            $config->string('app', 'demo');
            self::fail('Expected invalid config exception.');
        } catch (InvalidConfigValueException $exception) {
            self::assertStringNotContainsString('secret', $exception->getMessage());
            self::assertStringNotContainsString('token', $exception->getMessage());
        }
    }
}
