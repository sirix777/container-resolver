<?php

declare(strict_types=1);

namespace Sirix\ContainerResolver\Exception;

use RuntimeException;

use function sprintf;

final class MissingConfigValueException extends RuntimeException implements ConfigReaderException
{
    public static function forPath(string $path, ?string $context = null): self
    {
        $message = null === $context
            ? sprintf('Configuration value "%s" is required but is not defined.', $path)
            : sprintf('Configuration value "%s" is required by %s but is not defined.', $path, $context);

        return new self($message);
    }
}
