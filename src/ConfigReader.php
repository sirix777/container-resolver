<?php

declare(strict_types=1);

namespace Sirix\ContainerResolver;

use BackedEnum;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use ReflectionEnum;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\ContainerResolver\Exception\InvalidContainerServiceException;
use Sirix\ContainerResolver\Exception\MissingConfigValueException;
use Sirix\ContainerResolver\Exception\MissingContainerServiceException;
use Throwable;
use UnitEnum;

use function array_is_list;
use function array_key_exists;
use function enum_exists;
use function explode;
use function in_array;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function is_subclass_of;
use function strcasecmp;
use function trim;

final readonly class ConfigReader
{
    /**
     * @param array<string, mixed> $config
     *
     * @throws InvalidArgumentException when the root configuration is not a string-keyed map
     */
    public function __construct(private array $config, private ?string $context = null)
    {
        $this->assertMapDefault($config, 'The configuration root');
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws InvalidArgumentException when the root configuration is not a string-keyed map
     */
    public static function fromArray(array $config, ?string $context = null): self
    {
        return new self($config, $context);
    }

    /**
     * @throws MissingContainerServiceException      when the container reports the config service exists but cannot find it during resolution
     * @throws InvalidContainerServiceException      when the config service exists but is not an array
     * @throws ContainerExceptionInterface&Throwable when the underlying container fails to resolve the config service
     */
    public static function fromContainer(ContainerResolver $containerResolver, string $serviceId = 'config'): self
    {
        return new self($containerResolver->optionalMap($serviceId), $containerResolver->context());
    }

    /**
     * @throws InvalidArgumentException when the path is blank or contains an empty segment
     */
    public function has(string $path): bool
    {
        return $this->find($path)[0];
    }

    /**
     * @throws InvalidArgumentException when the path is blank or contains an empty segment
     */
    public function get(string $path, mixed $default = null): mixed
    {
        [$exists, $value] = $this->find($path);

        if (! $exists) {
            return $default;
        }

        return $value;
    }

    /**
     * @throws MissingConfigValueException when the config path is not defined
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function required(string $path): mixed
    {
        [$exists, $value] = $this->find($path);

        if (! $exists) {
            throw MissingConfigValueException::forPath($path, $this->context);
        }

        return $value;
    }

    /**
     * @throws InvalidConfigValueException when the config path exists but is not a string
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function string(string $path, string $default): string
    {
        $default          = trim($default);
        [$exists, $value] = $this->find($path);

        if (! $exists) {
            return $default;
        }

        return $this->assertString($path, $value);
    }

    /**
     * @throws MissingConfigValueException when the config path is not defined
     * @throws InvalidConfigValueException when the config path exists but is not a string
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function requiredString(string $path): string
    {
        return $this->assertString($path, $this->required($path));
    }

    /**
     * @throws InvalidConfigValueException when the config path exists but is not a non-empty string
     * @throws InvalidArgumentException    when the default is blank or the path is invalid
     */
    public function nonEmptyString(string $path, string $default): string
    {
        $default          = $this->assertNonEmptyStringDefault($default);
        [$exists, $value] = $this->find($path);

        if (! $exists) {
            return $default;
        }

        return $this->assertNonEmptyString($path, $value);
    }

    /**
     * @throws MissingConfigValueException when the config path is not defined
     * @throws InvalidConfigValueException when the config path exists but is not a non-empty string
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function requiredNonEmptyString(string $path): string
    {
        return $this->assertNonEmptyString($path, $this->required($path));
    }

    /**
     * @throws InvalidConfigValueException when the config path exists but is not a string
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function optionalString(string $path): ?string
    {
        [$exists, $value] = $this->find($path);

        if (! $exists) {
            return null;
        }

        return $this->assertString($path, $value);
    }

    /**
     * @throws InvalidConfigValueException when the config path exists but is not a string
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function optionalNonEmptyString(string $path): ?string
    {
        [$exists, $value] = $this->find($path);

        if (! $exists) {
            return null;
        }

        $value = $this->assertString($path, $value);

        if ('' === $value) {
            return null;
        }

        return $value;
    }

    /**
     * @throws InvalidConfigValueException when the config path exists but is not a bool
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function bool(string $path, bool $default): bool
    {
        [$exists, $value] = $this->find($path);

        if (! $exists) {
            return $default;
        }

        return $this->assertBool($path, $value);
    }

    /**
     * @throws MissingConfigValueException when the config path is not defined
     * @throws InvalidConfigValueException when the config path exists but is not a bool
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function requiredBool(string $path): bool
    {
        return $this->assertBool($path, $this->required($path));
    }

    /**
     * @throws InvalidConfigValueException when the config path exists but is not an int
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function int(string $path, int $default): int
    {
        [$exists, $value] = $this->find($path);

        if (! $exists) {
            return $default;
        }

        return $this->assertInt($path, $value);
    }

    /**
     * @throws MissingConfigValueException when the config path is not defined
     * @throws InvalidConfigValueException when the config path exists but is not an int
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function requiredInt(string $path): int
    {
        return $this->assertInt($path, $this->required($path));
    }

    /**
     * @param array<mixed> $default
     *
     * @return array<mixed>
     *
     * @throws InvalidConfigValueException when the config path exists but is not an array
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function array(string $path, array $default): array
    {
        [$exists, $value] = $this->find($path);

        if (! $exists) {
            return $default;
        }

        return $this->assertArray($path, $value);
    }

    /**
     * @return array<mixed>
     *
     * @throws MissingConfigValueException when the config path is not defined
     * @throws InvalidConfigValueException when the config path exists but is not an array
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function requiredArray(string $path): array
    {
        return $this->assertArray($path, $this->required($path));
    }

    /**
     * @param array<mixed> $default
     *
     * @return list<mixed>
     *
     * @throws InvalidConfigValueException when the config path exists but is not a list
     * @throws InvalidArgumentException    when the default is not a list or the path is invalid
     */
    public function list(string $path, array $default): array
    {
        $default          = $this->assertListDefault($default);
        [$exists, $value] = $this->find($path);

        if (! $exists) {
            return $default;
        }

        return $this->assertList($path, $value);
    }

    /**
     * @return list<mixed>
     *
     * @throws MissingConfigValueException when the config path is not defined
     * @throws InvalidConfigValueException when the config path exists but is not a list
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function requiredList(string $path): array
    {
        return $this->assertList($path, $this->required($path));
    }

    /**
     * @param array<mixed> $default
     *
     * @return list<string>
     *
     * @throws InvalidConfigValueException when the config path exists but is not a list of strings
     * @throws InvalidArgumentException    when the default is invalid or the path is invalid
     */
    public function stringList(string $path, array $default): array
    {
        $default          = $this->assertStringListDefault($default);
        [$exists, $value] = $this->find($path);

        if (! $exists) {
            return $default;
        }

        return $this->assertStringList($path, $value);
    }

    /**
     * @return list<string>
     *
     * @throws MissingConfigValueException when the config path is not defined
     * @throws InvalidConfigValueException when the config path exists but is not a list of strings
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function requiredStringList(string $path): array
    {
        return $this->assertStringList($path, $this->required($path));
    }

    /**
     * @param array<mixed> $default
     *
     * @return list<non-empty-string>
     *
     * @throws InvalidConfigValueException when the config path exists but is not a list of non-empty strings
     * @throws InvalidArgumentException    when the default is invalid or the path is invalid
     */
    public function nonEmptyStringList(string $path, array $default): array
    {
        $default          = $this->assertNonEmptyStringListDefault($default);
        [$exists, $value] = $this->find($path);

        if (! $exists) {
            return $default;
        }

        return $this->assertNonEmptyStringList($path, $value);
    }

    /**
     * @return list<non-empty-string>
     *
     * @throws MissingConfigValueException when the config path is not defined
     * @throws InvalidConfigValueException when the config path exists but is not a list of non-empty strings
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function requiredNonEmptyStringList(string $path): array
    {
        return $this->assertNonEmptyStringList($path, $this->required($path));
    }

    /**
     * @param array<mixed> $default
     *
     * @return array<string, mixed>
     *
     * @throws InvalidConfigValueException when the config path exists but is not a string-keyed map
     * @throws InvalidArgumentException    when the default is not a map or the path is invalid
     */
    public function map(string $path, array $default): array
    {
        $default          = $this->assertMapDefault($default, 'The default value');
        [$exists, $value] = $this->find($path);

        if (! $exists) {
            return $default;
        }

        return $this->assertMap($path, $value);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws MissingConfigValueException when the config path is not defined
     * @throws InvalidConfigValueException when the config path exists but is not a string-keyed map
     * @throws InvalidArgumentException    when the path is blank or contains an empty segment
     */
    public function requiredMap(string $path): array
    {
        return $this->assertMap($path, $this->required($path));
    }

    /**
     * @template T of UnitEnum
     *
     * @param class-string<T> $enumClass
     * @param T               $default
     *
     * @return T
     *
     * @throws InvalidConfigValueException when the config path exists but cannot be resolved to the requested enum
     * @throws InvalidArgumentException    when the enum class/default is invalid or the path is invalid
     */
    public function enum(string $path, string $enumClass, UnitEnum $default): UnitEnum
    {
        $this->assertEnumClass($enumClass);

        if (! $default instanceof $enumClass) {
            throw new InvalidArgumentException('The enum default must be an instance of the requested enum class.');
        }

        [$exists, $value] = $this->find($path);

        if (! $exists) {
            return $default;
        }

        return $this->assertEnum($path, $enumClass, $value);
    }

    /**
     * @template T of UnitEnum
     *
     * @param class-string<T> $enumClass
     *
     * @return T
     *
     * @throws MissingConfigValueException when the config path is not defined
     * @throws InvalidConfigValueException when the config path exists but cannot be resolved to the requested enum
     * @throws InvalidArgumentException    when the enum class or path is invalid
     */
    public function requiredEnum(string $path, string $enumClass): UnitEnum
    {
        $this->assertEnumClass($enumClass);

        return $this->assertEnum($path, $enumClass, $this->required($path));
    }

    /**
     * @param array<mixed> $allowed
     *
     * @throws InvalidConfigValueException when the config path exists but is not one of the allowed strings
     * @throws InvalidArgumentException    when the allowed values/default is invalid or the path is invalid
     */
    public function stringEnum(string $path, array $allowed, string $default): string
    {
        $allowed = $this->assertAllowedValues($allowed);
        $default = trim($default);

        if (! in_array($default, $allowed, true)) {
            throw new InvalidArgumentException('The string enum default must be one of the allowed values.');
        }

        [$exists, $value] = $this->find($path);

        if (! $exists) {
            return $default;
        }

        $value = $this->assertString($path, $value);

        if (! in_array($value, $allowed, true)) {
            throw InvalidConfigValueException::forAllowedValues($path, $allowed, $value, $this->context);
        }

        return $value;
    }

    /**
     * @param array<mixed> $allowed
     *
     * @throws MissingConfigValueException when the config path is not defined
     * @throws InvalidConfigValueException when the config path exists but is not one of the allowed strings
     * @throws InvalidArgumentException    when the allowed values or path is invalid
     */
    public function requiredStringEnum(string $path, array $allowed): string
    {
        $allowed = $this->assertAllowedValues($allowed);
        $value   = $this->assertString($path, $this->required($path));

        if (! in_array($value, $allowed, true)) {
            throw InvalidConfigValueException::forAllowedValues($path, $allowed, $value, $this->context);
        }

        return $value;
    }

    /**
     * @template T of UnitEnum
     *
     * @param class-string<T> $enumClass
     *
     * @return T
     */
    private function assertEnum(string $path, string $enumClass, mixed $value): UnitEnum
    {
        if ($value instanceof $enumClass) {
            return $value;
        }

        $enum = $this->enumFromBackedValue($enumClass, $value)
            ?? $this->enumFromCaseName($enumClass, $value);

        if ($enum instanceof UnitEnum) {
            return $enum;
        }

        $allowed = $this->enumAllowedValues($enumClass);
        if ([] !== $allowed) {
            // @var non-empty-list<string> $allowed
            throw InvalidConfigValueException::forAllowedValues($path, $allowed, $value, $this->context);
        }

        throw InvalidConfigValueException::forType($path, $enumClass, $value, $this->context);
    }

    /**
     * @template T of UnitEnum
     *
     * @param class-string<T> $enumClass
     *
     * @return null|T
     */
    private function enumFromBackedValue(string $enumClass, mixed $value): ?UnitEnum
    {
        if (! is_subclass_of($enumClass, BackedEnum::class)) {
            return null;
        }

        $backingType = (new ReflectionEnum($enumClass))->getBackingType()?->getName();

        if ('string' === $backingType) {
            if (! is_string($value)) {
                return null;
            }

            // @var class-string<T&BackedEnum> $enumClass
            return $enumClass::tryFrom(trim($value));
        }

        if ('int' !== $backingType || ! is_int($value)) {
            return null;
        }

        // @var class-string<T&BackedEnum> $enumClass
        return $enumClass::tryFrom($value);
    }

    /**
     * @template T of UnitEnum
     *
     * @param class-string<T> $enumClass
     *
     * @return null|T
     */
    private function enumFromCaseName(string $enumClass, mixed $value): ?UnitEnum
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        foreach ($enumClass::cases() as $unitEnum) {
            if ($unitEnum->name === $value) {
                return $unitEnum;
            }
        }

        foreach ($enumClass::cases() as $unitEnum) {
            if (0 === strcasecmp($unitEnum->name, $value)) {
                return $unitEnum;
            }
        }

        return null;
    }

    /**
     * @template T of UnitEnum
     *
     * @param class-string<T> $enumClass
     *
     * @return list<string>
     */
    private function enumAllowedValues(string $enumClass): array
    {
        $allowed = [];

        foreach ($enumClass::cases() as $unitEnum) {
            $allowed[] = "case {$unitEnum->name}";

            if ($unitEnum instanceof BackedEnum) {
                $backedValue = is_string($unitEnum->value) ? $unitEnum->value : (string) $unitEnum->value;
                $allowed[]   = "backed value {$backedValue}";
            }
        }

        return $allowed;
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function find(string $path): array
    {
        if ('' === $path || '' === trim($path)) {
            throw new InvalidArgumentException('The configuration path must not be blank.');
        }

        $value = $this->config;

        foreach (explode('.', $path) as $segment) {
            if ('' === $segment) {
                throw new InvalidArgumentException('The configuration path must not contain empty segments.');
            }

            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return [false, null];
            }

            $value = $value[$segment];
        }

        return [true, $value];
    }

    private function assertString(string $path, mixed $value): string
    {
        if (! is_string($value)) {
            throw InvalidConfigValueException::forType($path, 'string', $value, $this->context);
        }

        return trim($value);
    }

    /**
     * @return non-empty-string
     */
    private function assertNonEmptyString(string $path, mixed $value): string
    {
        $value = $this->assertString($path, $value);

        if ('' === $value) {
            throw InvalidConfigValueException::forType($path, 'non-empty-string', $value, $this->context);
        }

        return $value;
    }

    private function assertBool(string $path, mixed $value): bool
    {
        if (! is_bool($value)) {
            throw InvalidConfigValueException::forType($path, 'bool', $value, $this->context);
        }

        return $value;
    }

    private function assertInt(string $path, mixed $value): int
    {
        if (! is_int($value)) {
            throw InvalidConfigValueException::forType($path, 'int', $value, $this->context);
        }

        return $value;
    }

    /**
     * @return array<mixed>
     */
    private function assertArray(string $path, mixed $value): array
    {
        if (! is_array($value)) {
            throw InvalidConfigValueException::forType($path, 'array', $value, $this->context);
        }

        return $value;
    }

    /**
     * @return list<mixed>
     */
    private function assertList(string $path, mixed $value): array
    {
        $value = $this->assertArray($path, $value);

        if (! array_is_list($value)) {
            throw InvalidConfigValueException::forType($path, 'list<mixed>', $value, $this->context);
        }

        // @var list<mixed> $value
        return $value;
    }

    /**
     * @return list<string>
     */
    private function assertStringList(string $path, mixed $value): array
    {
        $value = $this->assertList($path, $value);

        foreach ($value as $index => $item) {
            $value[$index] = $this->assertString("{$path}.{$index}", $item);
        }

        // @var list<string> $value
        return $value;
    }

    /**
     * @return list<non-empty-string>
     */
    private function assertNonEmptyStringList(string $path, mixed $value): array
    {
        $value = $this->assertList($path, $value);

        foreach ($value as $index => $item) {
            $value[$index] = $this->assertNonEmptyString("{$path}.{$index}", $item);
        }

        // @var list<non-empty-string> $value
        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function assertMap(string $path, mixed $value): array
    {
        $value = $this->assertArray($path, $value);

        $map = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw InvalidConfigValueException::forType($path, 'map<string, mixed>', $value, $this->context);
            }

            $map[$key] = $item;
        }

        return $map;
    }

    /**
     * @param class-string<UnitEnum> $enumClass
     */
    private function assertEnumClass(string $enumClass): void
    {
        if (! enum_exists($enumClass)) {
            throw new InvalidArgumentException('The enum class must name an existing enum.');
        }
    }

    /**
     * @param array<mixed> $default
     *
     * @return list<mixed>
     */
    private function assertListDefault(array $default): array
    {
        if (! array_is_list($default)) {
            throw new InvalidArgumentException('The default value must be a list.');
        }

        return $default;
    }

    /**
     * @param array<mixed> $default
     *
     * @return list<string>
     */
    private function assertStringListDefault(array $default): array
    {
        $default = $this->assertListDefault($default);

        foreach ($default as $index => $item) {
            if (! is_string($item)) {
                throw new InvalidArgumentException("The string list default item at index {$index} must be a string.");
            }

            $default[$index] = trim($item);
        }

        // @var list<string> $default
        return $default;
    }

    /**
     * @param array<mixed> $default
     *
     * @return list<non-empty-string>
     */
    private function assertNonEmptyStringListDefault(array $default): array
    {
        $default = $this->assertStringListDefault($default);

        foreach ($default as $item) {
            if ('' === $item) {
                throw new InvalidArgumentException('The non-empty string list default must not contain blank strings.');
            }
        }

        // @var list<non-empty-string> $default
        return $default;
    }

    /**
     * @param array<mixed> $default
     *
     * @return array<string, mixed>
     */
    private function assertMapDefault(array $default, string $description): array
    {
        $map = [];

        foreach ($default as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException("{$description} must be a string-keyed map.");
            }

            $map[$key] = $value;
        }

        return $map;
    }

    /**
     * @param array<mixed> $allowed
     *
     * @return non-empty-list<string>
     */
    private function assertAllowedValues(array $allowed): array
    {
        if ([] === $allowed || ! array_is_list($allowed)) {
            throw new InvalidArgumentException('Allowed string enum values must be a non-empty list of strings.');
        }

        foreach ($allowed as $index => $value) {
            if (! is_string($value)) {
                throw new InvalidArgumentException("The allowed string enum value at index {$index} must be a string.");
            }

            $allowed[$index] = trim($value);
        }

        // @var non-empty-list<string> $allowed
        return $allowed;
    }

    /**
     * @return non-empty-string
     */
    private function assertNonEmptyStringDefault(string $default): string
    {
        $default = trim($default);

        if ('' === $default) {
            throw new InvalidArgumentException('The non-empty string default must not be blank.');
        }

        return $default;
    }
}
