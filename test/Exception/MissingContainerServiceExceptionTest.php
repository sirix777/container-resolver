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
        $runtimeException = new RuntimeException('previous');
        $missingContainerServiceException = MissingContainerServiceException::forService('config', 'factory', $runtimeException);

        self::assertInstanceOf(NotFoundExceptionInterface::class, $missingContainerServiceException);
        self::assertInstanceOf(ContainerResolverException::class, $missingContainerServiceException);
        self::assertInstanceOf(ResolverException::class, $missingContainerServiceException);
        self::assertSame('Container service "config" is required by factory but is not registered.', $missingContainerServiceException->getMessage());
        self::assertSame($runtimeException, $missingContainerServiceException->getPrevious());
    }
}
