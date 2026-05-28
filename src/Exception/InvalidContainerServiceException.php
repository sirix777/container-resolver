<?php

declare(strict_types=1);

namespace Sirix\ContainerResolver\Exception;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

use function get_debug_type;
use function sprintf;

final class InvalidContainerServiceException extends RuntimeException implements ContainerExceptionInterface, ContainerResolverException
{
    public static function forType(string $serviceId, string $expectedType, mixed $actual, ?string $context = null): self
    {
        $message = null === $context
            ? sprintf(
                'Container service "%s" must be %s; %s given.',
                $serviceId,
                $expectedType,
                self::actualType($actual),
            )
            : sprintf(
                'Container service "%s" required by %s must be %s; %s given.',
                $serviceId,
                $context,
                $expectedType,
                self::actualType($actual),
            );

        return new self($message);
    }

    private static function actualType(mixed $actual): string
    {
        return get_debug_type($actual);
    }
}
