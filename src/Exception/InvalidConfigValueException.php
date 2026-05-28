<?php

declare(strict_types=1);

namespace Sirix\ContainerResolver\Exception;

use RuntimeException;

use function array_map;
use function get_debug_type;
use function implode;
use function is_string;
use function sprintf;

final class InvalidConfigValueException extends RuntimeException implements ConfigReaderException
{
    public static function forType(string $path, string $expectedType, mixed $actual, ?string $context = null): self
    {
        $message = null === $context
            ? sprintf(
                'Configuration value "%s" must be %s; %s given.',
                $path,
                $expectedType,
                self::actualType($actual),
            )
            : sprintf(
                'Configuration value "%s" required by %s must be %s; %s given.',
                $path,
                $context,
                $expectedType,
                self::actualType($actual),
            );

        return new self($message);
    }

    /**
     * @param non-empty-list<string> $allowed
     */
    public static function forAllowedValues(string $path, array $allowed, mixed $actual, ?string $context = null): self
    {
        $allowedValues = implode(', ', array_map(
            static fn (string $value): string => sprintf('"%s"', $value),
            $allowed,
        ));

        $message = null === $context
            ? sprintf(
                'Configuration value "%s" must be one of %s; %s given.',
                $path,
                $allowedValues,
                self::actualValueForAllowedValues($actual),
            )
            : sprintf(
                'Configuration value "%s" required by %s must be one of %s; %s given.',
                $path,
                $context,
                $allowedValues,
                self::actualValueForAllowedValues($actual),
            );

        return new self($message);
    }

    private static function actualType(mixed $actual): string
    {
        return get_debug_type($actual);
    }

    private static function actualValueForAllowedValues(mixed $actual): string
    {
        if (is_string($actual)) {
            return sprintf('"%s"', $actual);
        }

        return self::actualType($actual);
    }
}
