<?php

declare(strict_types=1);

namespace SirixTest\ContainerResolver\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sirix\ContainerResolver\Exception\ConfigReaderException;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\ContainerResolver\Exception\ResolverException;

#[CoversClass(InvalidConfigValueException::class)]
final class InvalidConfigValueExceptionTest extends TestCase
{
    public function testForTypeCreatesConfigException(): void
    {
        $exception = InvalidConfigValueException::forType('app.name', 'string', 123, 'factory');

        self::assertInstanceOf(ConfigReaderException::class, $exception);
        self::assertInstanceOf(ResolverException::class, $exception);
        self::assertSame('Configuration value "app.name" required by factory must be string; int given.', $exception->getMessage());
    }

    public function testForAllowedValuesIncludesStringActualValue(): void
    {
        $exception = InvalidConfigValueException::forAllowedValues('driver', ['bearer', 'cookie'], 'redis', 'factory');

        self::assertSame(
            'Configuration value "driver" required by factory must be one of "bearer", "cookie"; "redis" given.',
            $exception->getMessage(),
        );
    }
}
