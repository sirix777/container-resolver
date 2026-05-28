<?php

declare(strict_types=1);

namespace Sirix\ContainerResolver\Exception;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Throwable;

use function sprintf;

final class MissingContainerServiceException extends RuntimeException implements NotFoundExceptionInterface, ContainerResolverException
{
    public static function forService(string $serviceId, ?string $context = null, ?Throwable $previous = null): self
    {
        $message = null === $context
            ? sprintf('Container service "%s" is not registered.', $serviceId)
            : sprintf('Container service "%s" is required by %s but is not registered.', $serviceId, $context);

        return new self($message, previous: $previous);
    }
}
