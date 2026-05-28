<?php

declare(strict_types=1);

namespace SirixTest\ContainerResolver\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Sirix\ContainerResolver\Exception\ContainerResolverException;
use Sirix\ContainerResolver\Exception\MissingContainerServiceException;
use Sirix\ContainerResolver\Exception\ResolverException;

#[CoversClass(MissingContainerServiceException::class)]
final class MissingContainerServiceExceptionTest extends TestCase
{
    public function testForServiceCreatesNotFoundExceptionWithPreviousException(): void
    {
        $previous = new RuntimeException('previous');
        $exception = MissingContainerServiceException::forService('config', 'factory', $previous);

        self::assertInstanceOf(NotFoundExceptionInterface::class, $exception);
        self::assertInstanceOf(ContainerResolverException::class, $exception);
        self::assertInstanceOf(ResolverException::class, $exception);
        self::assertSame('Container service "config" is required by factory but is not registered.', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
    }
}
