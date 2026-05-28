<?php

declare(strict_types=1);

namespace Sirix\ContainerResolver;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\ContainerResolver\Exception\InvalidContainerServiceException;
use Sirix\ContainerResolver\Exception\MissingContainerServiceException;

use function is_array;

final readonly class ContainerResolver
{
    public function __construct(private ContainerInterface $container, private ?string $context = null) {}

    public static function forContext(ContainerInterface $container, string $context): self
    {
        return new self($container, $context);
    }

    /**
     * @param class-string $factoryClass
     */
    public static function forFactory(ContainerInterface $container, string $factoryClass): self
    {
        return new self($container, $factoryClass);
    }

    public function context(): ?string
    {
        return $this->context;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $serviceId
     *
     * @return T
     */
    public function get(string $serviceId): object
    {
        return $this->getAs($serviceId, $serviceId);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $expectedType
     *
     * @return T
     */
    public function getAs(string $serviceId, string $expectedType): object
    {
        $service = $this->getExisting($serviceId);

        if (! $service instanceof $expectedType) {
            throw InvalidContainerServiceException::forType(
                $serviceId,
                $expectedType,
                $service,
                $this->context,
            );
        }

        return $service;
    }

    public function getExisting(string $serviceId): mixed
    {
        if (! $this->has($serviceId)) {
            throw MissingContainerServiceException::forService($serviceId, $this->context);
        }

        try {
            return $this->container->get($serviceId);
        } catch (NotFoundExceptionInterface $exception) {
            throw MissingContainerServiceException::forService(
                $serviceId,
                $this->context,
                $exception,
            );
        }
    }

    public function has(string $serviceId): bool
    {
        return $this->container->has($serviceId);
    }

    public function optional(string $serviceId, mixed $default = null): mixed
    {
        if (! $this->has($serviceId)) {
            return $default;
        }

        return $this->getExisting($serviceId);
    }

    /**
     * @return array<string, mixed>
     */
    public function optionalArray(string $serviceId = 'config'): array
    {
        if (! $this->has($serviceId)) {
            return [];
        }

        $service = $this->getExisting($serviceId);

        if (! is_array($service)) {
            throw InvalidContainerServiceException::forType(
                $serviceId,
                'array',
                $service,
                $this->context,
            );
        }

        // @var array<string, mixed> $service
        return $service;
    }
}
