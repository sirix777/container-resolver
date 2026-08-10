<?php

declare(strict_types=1);

namespace Sirix\ContainerResolver;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\ContainerResolver\Exception\InvalidContainerServiceException;
use Sirix\ContainerResolver\Exception\MissingContainerServiceException;

use function is_array;
use function is_string;

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
     *
     * @throws MissingContainerServiceException when the service is not registered
     * @throws InvalidContainerServiceException when the service does not match the requested class or interface
     * @throws ContainerExceptionInterface      when the underlying container fails to resolve the service
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
     *
     * @throws MissingContainerServiceException when the service is not registered
     * @throws InvalidContainerServiceException when the service does not match the expected type
     * @throws ContainerExceptionInterface      when the underlying container fails to resolve the service
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

    /**
     * @throws MissingContainerServiceException when the service is not registered
     * @throws ContainerExceptionInterface      when the underlying container fails to resolve the service
     */
    public function getExisting(string $serviceId): mixed
    {
        [$exists, $service] = $this->findExisting($serviceId);

        if (! $exists) {
            throw MissingContainerServiceException::forService($serviceId, $this->context);
        }

        return $service;
    }

    public function has(string $serviceId): bool
    {
        return $this->container->has($serviceId);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $serviceId
     *
     * @return null|T
     *
     * @throws MissingContainerServiceException when the container reports the service exists but cannot find it during resolution
     * @throws InvalidContainerServiceException when the service exists but does not match the requested class or interface
     * @throws ContainerExceptionInterface      when the underlying container fails to resolve the service
     */
    public function optional(string $serviceId): ?object
    {
        return $this->optionalAs($serviceId, $serviceId);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $expectedType
     *
     * @return null|T
     *
     * @throws MissingContainerServiceException when the container reports the service exists but cannot find it during resolution
     * @throws InvalidContainerServiceException when the service exists but does not match the expected type
     * @throws ContainerExceptionInterface      when the underlying container fails to resolve the service
     */
    public function optionalAs(string $serviceId, string $expectedType): ?object
    {
        [$exists, $service] = $this->findExisting($serviceId);

        if (! $exists) {
            return null;
        }

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

    /**
     * @throws MissingContainerServiceException when the container reports the service exists but cannot find it during resolution
     * @throws ContainerExceptionInterface      when the underlying container fails to resolve the service
     */
    public function optionalExisting(string $serviceId, mixed $default = null): mixed
    {
        [$exists, $service] = $this->findExisting($serviceId);

        return $exists ? $service : $default;
    }

    /**
     * @return array<mixed>
     *
     * @throws MissingContainerServiceException when the container reports the service exists but cannot find it during resolution
     * @throws InvalidContainerServiceException when the service exists but is not an array
     * @throws ContainerExceptionInterface      when the underlying container fails to resolve the service
     */
    public function optionalArray(string $serviceId = 'config'): array
    {
        [$exists, $service] = $this->findExisting($serviceId);

        if (! $exists) {
            return [];
        }

        if (! is_array($service)) {
            throw InvalidContainerServiceException::forType(
                $serviceId,
                'array',
                $service,
                $this->context,
            );
        }

        return $service;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws MissingContainerServiceException when the container reports the service exists but cannot find it during resolution
     * @throws InvalidContainerServiceException when the service exists but is not a string-keyed array
     * @throws ContainerExceptionInterface      when the underlying container fails to resolve the service
     */
    public function optionalMap(string $serviceId = 'config'): array
    {
        $service = $this->optionalArray($serviceId);

        foreach ($service as $key => $_) {
            if (! is_string($key)) {
                throw InvalidContainerServiceException::forType(
                    $serviceId,
                    'array with string keys',
                    $service,
                    $this->context,
                );
            }
        }

        /** @var array<string, mixed> $service */
        return $service;
    }

    /**
     * @return array{0: bool, 1: mixed}
     *
     * @throws MissingContainerServiceException when the container reports the service exists but cannot find it during resolution
     * @throws ContainerExceptionInterface      when the underlying container fails to resolve the service
     */
    private function findExisting(string $serviceId): array
    {
        if (! $this->has($serviceId)) {
            return [false, null];
        }

        try {
            return [true, $this->container->get($serviceId)];
        } catch (NotFoundExceptionInterface $exception) {
            throw MissingContainerServiceException::forService(
                $serviceId,
                $this->context,
                $exception,
            );
        }
    }
}
