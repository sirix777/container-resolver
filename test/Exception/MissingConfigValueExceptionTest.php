<?php

declare(strict_types=1);

namespace SirixTest\ContainerResolver\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sirix\ContainerResolver\Exception\ConfigReaderException;
use Sirix\ContainerResolver\Exception\MissingConfigValueException;
use Sirix\ContainerResolver\Exception\ResolverException;

#[CoversClass(MissingConfigValueException::class)]
final class MissingConfigValueExceptionTest extends TestCase
{
    public function testForPathCreatesConfigException(): void
    {
        $missingConfigValueException = MissingConfigValueException::forPath('app.name', 'factory');

        self::assertInstanceOf(ConfigReaderException::class, $missingConfigValueException);
        self::assertInstanceOf(ResolverException::class, $missingConfigValueException);
        self::assertSame('Configuration value "app.name" is required by factory but is not defined.', $missingConfigValueException->getMessage());
    }
}
