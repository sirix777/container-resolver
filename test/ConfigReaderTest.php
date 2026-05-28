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
        $containerResolver = ContainerResolver::forContext(new ArrayContainer([
            'config' => [
                'app' => [
                    'name' => 'demo',
                ],
            ],
        ]), 'factory');

        $configReader = ConfigReader::fromContainer($containerResolver);

        self::assertSame('demo', $configReader->requiredString('app.name'));

        try {
            $configReader->requiredString('app.missing');
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
        $configReader = ConfigReader::fromContainer(new ContainerResolver(new ArrayContainer()));

        self::assertFalse($configReader->has('app.name'));
    }

    public function testHasReturnsTrueForExistingPathEvenWhenValueIsNull(): void
    {
        $configReader = ConfigReader::fromArray([
            'app' => [
                'name' => null,
            ],
        ]);

        self::assertTrue($configReader->has('app.name'));
    }

    public function testHasReturnsFalseForMissingPath(): void
    {
        $configReader = ConfigReader::fromArray([
            'app' => [],
        ]);

        self::assertFalse($configReader->has('app.name'));
    }

    public function testGetReturnsConfiguredValue(): void
    {
        $configReader = ConfigReader::fromArray([
            'app' => [
                'name' => 'demo',
            ],
        ]);

        self::assertSame('demo', $configReader->get('app.name'));
    }

    public function testGetReturnsDefaultForMissingValue(): void
    {
        $configReader = ConfigReader::fromArray([]);

        self::assertSame('demo', $configReader->get('app.name', 'demo'));
    }

    public function testRequiredThrowsMissingExceptionForMissingPath(): void
    {
        $configReader = ConfigReader::fromArray([], 'factory');

        $this->expectException(MissingConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "app.name" is required by factory but is not defined.');

        $configReader->required('app.name');
    }

    public function testStringReturnsDefaultWhenMissing(): void
    {
        $configReader = ConfigReader::fromArray([]);

        self::assertSame('demo', $configReader->string('app.name', 'demo'));
    }

    public function testStringReturnsConfiguredString(): void
    {
        $configReader = ConfigReader::fromArray(['app' => ['name' => 'api']]);

        self::assertSame('api', $configReader->string('app.name', 'demo'));
    }

    public function testStringTrimsConfiguredString(): void
    {
        $configReader = ConfigReader::fromArray(['app' => ['name' => ' api ']]);

        self::assertSame('api', $configReader->string('app.name', 'demo'));
    }

    public function testStringRejectsNonStringExistingValue(): void
    {
        $configReader = ConfigReader::fromArray(['app' => ['name' => 123]]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "app.name" must be string; int given.');

        $configReader->string('app.name', 'demo');
    }

    public function testRequiredStringThrowsWhenMissing(): void
    {
        $configReader = ConfigReader::fromArray([]);

        $this->expectException(MissingConfigValueException::class);

        $configReader->requiredString('app.name');
    }

    public function testRequiredStringRejectsNonStringExistingValue(): void
    {
        $configReader = ConfigReader::fromArray(['app' => ['name' => false]]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "app.name" must be string; bool given.');

        $configReader->requiredString('app.name');
    }

    public function testNonEmptyStringRejectsEmptyString(): void
    {
        $configReader = ConfigReader::fromArray(['app' => ['name' => '']]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "app.name" must be non-empty-string; string given.');

        $configReader->nonEmptyString('app.name', 'demo');
    }

    public function testNonEmptyStringTrimsAndRejectsBlankString(): void
    {
        $configReader = ConfigReader::fromArray(['app' => ['name' => ' demo ']]);

        self::assertSame('demo', $configReader->nonEmptyString('app.name', 'fallback'));

        $blank = ConfigReader::fromArray(['app' => ['name' => '   ']]);

        $this->expectException(InvalidConfigValueException::class);

        $blank->nonEmptyString('app.name', 'fallback');
    }

    public function testRequiredNonEmptyStringRejectsMissingAndEmptyValues(): void
    {
        $configReader = ConfigReader::fromArray([]);

        try {
            $configReader->requiredNonEmptyString('app.name');
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
        $configReader = ConfigReader::fromArray([]);

        self::assertNull($configReader->optionalString('app.name'));
    }

    public function testOptionalStringReturnsStringWhenPresent(): void
    {
        $configReader = ConfigReader::fromArray(['app' => ['name' => 'demo']]);

        self::assertSame('demo', $configReader->optionalString('app.name'));
    }

    public function testOptionalStringTrimsStringWhenPresent(): void
    {
        $configReader = ConfigReader::fromArray(['app' => ['name' => ' demo ']]);

        self::assertSame('demo', $configReader->optionalString('app.name'));
    }

    public function testOptionalStringRejectsNonStringWhenPresent(): void
    {
        $configReader = ConfigReader::fromArray(['app' => ['name' => null]]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "app.name" must be string; null given.');

        $configReader->optionalString('app.name');
    }

    public function testOptionalNonEmptyStringReturnsNullWhenMissing(): void
    {
        $configReader = ConfigReader::fromArray([]);

        self::assertNull($configReader->optionalNonEmptyString('app.name'));
    }

    public function testOptionalNonEmptyStringReturnsNullForEmptyString(): void
    {
        $configReader = ConfigReader::fromArray(['app' => ['name' => '']]);

        self::assertNull($configReader->optionalNonEmptyString('app.name'));
    }

    public function testOptionalNonEmptyStringReturnsNullForBlankString(): void
    {
        $configReader = ConfigReader::fromArray(['app' => ['name' => '   ']]);

        self::assertNull($configReader->optionalNonEmptyString('app.name'));
    }

    public function testOptionalNonEmptyStringReturnsStringWhenPresent(): void
    {
        $configReader = ConfigReader::fromArray(['app' => ['name' => ' demo ']]);

        self::assertSame('demo', $configReader->optionalNonEmptyString('app.name'));
    }

    public function testOptionalNonEmptyStringRejectsNonStringWhenPresent(): void
    {
        $configReader = ConfigReader::fromArray(['app' => ['name' => 123]]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "app.name" must be string; int given.');

        $configReader->optionalNonEmptyString('app.name');
    }

    public function testBoolAcceptsOnlyBool(): void
    {
        $configReader = ConfigReader::fromArray(['debug' => false]);

        self::assertFalse($configReader->bool('debug', true));
        self::assertTrue($configReader->bool('missing', true));
    }

    public function testBoolRejectsStringBooleans(): void
    {
        $configReader = ConfigReader::fromArray(['debug' => 'false']);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "debug" must be bool; string given.');

        $configReader->bool('debug', false);
    }

    public function testRequiredBoolRejectsMissingAndInvalidValues(): void
    {
        $configReader = ConfigReader::fromArray([]);

        try {
            $configReader->requiredBool('debug');
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
        $configReader = ConfigReader::fromArray(['port' => 8080]);

        self::assertSame(8080, $configReader->int('port', 80));
        self::assertSame(80, $configReader->int('missing', 80));
    }

    public function testIntRejectsNumericStrings(): void
    {
        $configReader = ConfigReader::fromArray(['port' => '8080']);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "port" must be int; string given.');

        $configReader->int('port', 80);
    }

    public function testRequiredIntRejectsMissingAndInvalidValues(): void
    {
        $configReader = ConfigReader::fromArray([]);

        try {
            $configReader->requiredInt('port');
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
        $configReader = ConfigReader::fromArray(['items' => ['a', 'b']]);

        self::assertSame(['a', 'b'], $configReader->array('items', []));
        self::assertSame(['fallback'], $configReader->array('missing', ['fallback']));
    }

    public function testArrayRejectsScalarValues(): void
    {
        $configReader = ConfigReader::fromArray(['items' => 'invalid']);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "items" must be array; string given.');

        $configReader->array('items', []);
    }

    public function testRequiredArrayRejectsMissingValues(): void
    {
        $configReader = ConfigReader::fromArray([]);

        $this->expectException(MissingConfigValueException::class);

        $configReader->requiredArray('items');
    }

    public function testListAcceptsListsAndReturnsDefaultWhenMissing(): void
    {
        $configReader = ConfigReader::fromArray(['entities' => ['src/App/src/Entity']]);

        self::assertSame(['src/App/src/Entity'], $configReader->list('entities', []));
        self::assertSame(['fallback'], $configReader->list('missing', ['fallback']));
    }

    public function testListRejectsMaps(): void
    {
        $configReader = ConfigReader::fromArray(['entities' => ['app' => 'src/App/src/Entity']]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "entities" must be list<mixed>; array given.');

        $configReader->list('entities', []);
    }

    public function testListRejectsScalarValues(): void
    {
        $configReader = ConfigReader::fromArray(['entities' => 'src/App/src/Entity']);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "entities" must be array; string given.');

        $configReader->list('entities', []);
    }

    public function testRequiredListRejectsMissingValues(): void
    {
        $configReader = ConfigReader::fromArray([]);

        $this->expectException(MissingConfigValueException::class);

        $configReader->requiredList('entities');
    }

    public function testStringListAcceptsStringListsAndReturnsDefaultWhenMissing(): void
    {
        $configReader = ConfigReader::fromArray(['entities' => [' src/App/src/Entity ']]);

        self::assertSame(['src/App/src/Entity'], $configReader->stringList('entities', []));
        self::assertSame(['fallback'], $configReader->stringList('missing', ['fallback']));
    }

    public function testStringListRejectsNonStringItems(): void
    {
        $configReader = ConfigReader::fromArray(['entities' => ['src/App/src/Entity', 123]]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "entities.1" must be string; int given.');

        $configReader->stringList('entities', []);
    }

    public function testRequiredStringListRejectsMissingValues(): void
    {
        $configReader = ConfigReader::fromArray([]);

        $this->expectException(MissingConfigValueException::class);

        $configReader->requiredStringList('entities');
    }

    public function testNonEmptyStringListAcceptsNonEmptyStringListsAndReturnsDefaultWhenMissing(): void
    {
        $configReader = ConfigReader::fromArray(['entities' => [' src/App/src/Entity ']]);

        self::assertSame(['src/App/src/Entity'], $configReader->nonEmptyStringList('entities', []));
        self::assertSame(['fallback'], $configReader->nonEmptyStringList('missing', ['fallback']));
    }

    public function testNonEmptyStringListRejectsEmptyStringItems(): void
    {
        $configReader = ConfigReader::fromArray(['entities' => ['']]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "entities.0" must be non-empty-string; string given.');

        $configReader->nonEmptyStringList('entities', []);
    }

    public function testRequiredNonEmptyStringListRejectsMissingValues(): void
    {
        $configReader = ConfigReader::fromArray([]);

        $this->expectException(MissingConfigValueException::class);

        $configReader->requiredNonEmptyStringList('entities');
    }

    public function testMapAcceptsStringKeyedArrays(): void
    {
        $configReader = ConfigReader::fromArray([
            'storages' => [
                'redis' => 'app.storage.redis',
                'db' => 'app.storage.db',
            ],
        ]);

        self::assertSame([
            'redis' => 'app.storage.redis',
            'db' => 'app.storage.db',
        ], $configReader->map('storages', []));
        self::assertSame(['default' => true], $configReader->map('missing', ['default' => true]));
    }

    public function testMapRejectsLists(): void
    {
        $configReader = ConfigReader::fromArray([
            'storages' => ['app.storage.redis'],
        ]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "storages" must be map<string, mixed>; array given.');

        $configReader->map('storages', []);
    }

    public function testRequiredMapRejectsMissingValues(): void
    {
        $configReader = ConfigReader::fromArray([]);

        $this->expectException(MissingConfigValueException::class);

        $configReader->requiredMap('storages');
    }

    public function testEnumReturnsDefaultWhenMissing(): void
    {
        $configReader = ConfigReader::fromArray([]);

        self::assertSame(LogLevel::Info, $configReader->enum('log.level', LogLevel::class, LogLevel::Info));
    }

    public function testEnumAcceptsExistingEnumInstances(): void
    {
        $configReader = ConfigReader::fromArray(['log' => ['level' => LogLevel::Warning]]);

        self::assertSame(LogLevel::Warning, $configReader->enum('log.level', LogLevel::class, LogLevel::Info));
    }

    public function testEnumResolvesCaseNameCaseInsensitively(): void
    {
        $configReader = ConfigReader::fromArray(['log' => ['level' => ' debug ']]);

        self::assertSame(LogLevel::Debug, $configReader->enum('log.level', LogLevel::class, LogLevel::Info));
    }

    public function testEnumResolvesBackedEnumValueStrictly(): void
    {
        $configReader = ConfigReader::fromArray([
            'driver' => ' cookie ',
            'log' => ['level' => 100],
        ]);

        self::assertSame(StringDriver::Cookie, $configReader->requiredEnum('driver', StringDriver::class));
        self::assertSame(LogLevel::Debug, $configReader->requiredEnum('log.level', LogLevel::class));
    }

    public function testEnumDoesNotCoerceNumericStringsForIntBackedEnums(): void
    {
        $configReader = ConfigReader::fromArray(['log' => ['level' => '100']]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "log.level" must be one of "Debug", "Info", "Warning"; "100" given.');

        $configReader->enum('log.level', LogLevel::class, LogLevel::Info);
    }

    public function testEnumSupportsUnitEnumsByCaseName(): void
    {
        $configReader = ConfigReader::fromArray(['mode' => 'Async']);

        self::assertSame(UnitMode::Async, $configReader->requiredEnum('mode', UnitMode::class));
    }

    public function testRequiredEnumRejectsMissingValues(): void
    {
        $configReader = ConfigReader::fromArray([]);

        $this->expectException(MissingConfigValueException::class);

        $configReader->requiredEnum('log.level', LogLevel::class);
    }

    public function testRequiredEnumRejectsUnsupportedValues(): void
    {
        $configReader = ConfigReader::fromArray(['log' => ['level' => 'verbose']]);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "log.level" must be one of "Debug", "Info", "Warning"; "verbose" given.');

        $configReader->requiredEnum('log.level', LogLevel::class);
    }

    public function testStringEnumAcceptsAllowedValues(): void
    {
        $configReader = ConfigReader::fromArray(['driver' => ' bearer ']);

        self::assertSame('bearer', $configReader->stringEnum('driver', ['bearer', 'cookie'], 'cookie'));
        self::assertSame('cookie', $configReader->stringEnum('missing', ['bearer', 'cookie'], 'cookie'));
    }

    public function testStringEnumRejectsUnsupportedValues(): void
    {
        $configReader = ConfigReader::fromArray(['driver' => 'redis']);

        $this->expectException(InvalidConfigValueException::class);
        $this->expectExceptionMessage('Configuration value "driver" must be one of "bearer", "cookie"; "redis" given.');

        $configReader->stringEnum('driver', ['bearer', 'cookie'], 'bearer');
    }

    public function testRequiredStringEnumRejectsMissingUnsupportedAndNonStringValues(): void
    {
        $configReader = ConfigReader::fromArray([]);

        try {
            $configReader->requiredStringEnum('driver', ['bearer', 'cookie']);
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
        $configReader = ConfigReader::fromArray(['app' => ['name' => 'first']]);
        $second = ConfigReader::fromArray(['app' => ['name' => 'second']]);

        self::assertSame('first', $configReader->requiredString('app.name'));
        self::assertSame('second', $second->requiredString('app.name'));
    }

    public function testExceptionMessagesDoNotDumpFullConfigArrays(): void
    {
        $configReader = ConfigReader::fromArray([
            'app' => [
                'secret' => 'token',
            ],
        ]);

        try {
            $configReader->string('app', 'demo');
            self::fail('Expected invalid config exception.');
        } catch (InvalidConfigValueException $exception) {
            self::assertStringNotContainsString('secret', $exception->getMessage());
            self::assertStringNotContainsString('token', $exception->getMessage());
        }
    }
}
