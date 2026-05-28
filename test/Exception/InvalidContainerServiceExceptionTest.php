<?php

declare(strict_types=1);

namespace SirixTest\ContainerResolver\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Sirix\ContainerResolver\Exception\ContainerResolverException;
use Sirix\ContainerResolver\Exception\InvalidContainerServiceException;
use Sirix\ContainerResolver\Exception\ResolverException;

#[CoversClass(InvalidContainerServiceException::class)]
final class InvalidContainerServiceExceptionTest extends TestCase
{
    public function testForTypeCreatesContainerException(): void
    {
        $exception = InvalidContainerServiceException::forType('config', 'array', 'invalid', 'factory');

        self::assertInstanceOf(ContainerExceptionInterface::class, $exception);
        self::assertInstanceOf(ContainerResolverException::class, $exception);
        self::assertInstanceOf(ResolverException::class, $exception);
        self::assertSame('Container service "config" required by factory must be array; string given.', $exception->getMessage());
    }
}
