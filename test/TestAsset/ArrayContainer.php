<?php

declare(strict_types=1);

namespace SirixTest\ContainerResolver\TestAsset;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

use function array_key_exists;

/**
 * @internal
 */
final readonly class ArrayContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $services
     * @param list<string> $notFoundOnGet
     */
    public function __construct(
        private array $services = [],
        private array $notFoundOnGet = [],
    ) {}

    public function get(string $id): mixed
    {
        if (in_array($id, $this->notFoundOnGet, true)) {
            throw new TestNotFoundException($id);
        }

        if (! $this->has($id)) {
            throw new TestNotFoundException($id);
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}

/**
 * @internal
 */
final class TestNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Service "%s" was not found.', $id));
    }
}
