<?php

declare(strict_types=1);

namespace SirixTest\ContainerResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\ContainerResolver\Exception\InvalidContainerServiceException;
use Sirix\ContainerResolver\Exception\MissingContainerServiceException;
use SirixTest\ContainerResolver\TestAsset\ArrayContainer;
use SirixTest\ContainerResolver\TestAsset\Contract;
use SirixTest\ContainerResolver\TestAsset\ExampleFactory;
use SirixTest\ContainerResolver\TestAsset\Implementation;
use SirixTest\ContainerResolver\TestAsset\OtherImplementation;

use function sprintf;

#[CoversClass(ContainerResolver::class)]
#[CoversClass(InvalidContainerServiceException::class)]
#[CoversClass(MissingContainerServiceException::class)]
final class ContainerResolverTest extends TestCase
{
    public function testGetReturnsRegisteredService(): void
    {
        $implementation    = new Implementation();
        $containerResolver = new ContainerResolver(new ArrayContainer([
            Implementation::class => $implementation,
        ]));

        self::assertSame($implementation, $containerResolver->get(Implementation::class));
    }

    public function testGetThrowsMissingExceptionWhenServiceIsNotRegistered(): void
    {
        $containerResolver = ContainerResolver::forFactory(new ArrayContainer(), ExampleFactory::class);

        $this->expectException(MissingContainerServiceException::class);
        $this->expectExceptionMessage(sprintf(
            'Container service "%s" is required by %s but is not registered.',
            Implementation::class,
            ExampleFactory::class,
        ));

        $containerResolver->get(Implementation::class);
    }

    public function testGetWrapsNotFoundExceptionThrownByContainer(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer(
            [
                Implementation::class => new Implementation(),
            ],
            [Implementation::class],
        ));

        try {
            $containerResolver->get(Implementation::class);
            self::fail('Expected missing service exception.');
        } catch (MissingContainerServiceException $exception) {
            self::assertSame(
                sprintf('Container service "%s" is not registered.', Implementation::class),
                $exception->getMessage(),
            );
            self::assertNotNull($exception->getPrevious());
        }
    }

    public function testGetThrowsInvalidExceptionWhenServiceDoesNotMatchServiceId(): void
    {
        $containerResolver = ContainerResolver::forFactory(new ArrayContainer([
            Implementation::class => new OtherImplementation(),
        ]), ExampleFactory::class);

        $this->expectException(InvalidContainerServiceException::class);
        $this->expectExceptionMessage(sprintf(
            'Container service "%s" required by %s must be %s; %s given.',
            Implementation::class,
            ExampleFactory::class,
            Implementation::class,
            OtherImplementation::class,
        ));

        $containerResolver->get(Implementation::class);
    }

    public function testGetAsReturnsCustomServiceIdWithExpectedType(): void
    {
        $implementation    = new Implementation();
        $containerResolver = new ContainerResolver(new ArrayContainer([
            'app.service' => $implementation,
        ]));

        self::assertSame($implementation, $containerResolver->getAs('app.service', Contract::class));
    }

    public function testGetAsThrowsInvalidExceptionForWrongType(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer([
            'app.service' => new OtherImplementation(),
        ]));

        $this->expectException(InvalidContainerServiceException::class);
        $this->expectExceptionMessage(sprintf(
            'Container service "app.service" must be %s; %s given.',
            Contract::class,
            OtherImplementation::class,
        ));

        $containerResolver->getAs('app.service', Contract::class);
    }

    public function testGetExistingReturnsRawServiceValue(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer([
            'scalar' => 'value',
        ]));

        self::assertSame('value', $containerResolver->getExisting('scalar'));
    }

    public function testGetExistingThrowsMissingExceptionForMissingService(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer());

        $this->expectException(MissingContainerServiceException::class);

        $containerResolver->getExisting('missing');
    }

    public function testHasProxiesContainerHas(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer([
            'known' => null,
        ]));

        self::assertTrue($containerResolver->has('known'));
        self::assertFalse($containerResolver->has('missing'));
    }

    public function testOptionalReturnsNullWhenServiceIsMissing(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer());

        self::assertNull($containerResolver->optional(Implementation::class));
    }

    public function testOptionalReturnsTypedServiceWhenPresent(): void
    {
        $implementation    = new Implementation();
        $containerResolver = new ContainerResolver(new ArrayContainer([
            Implementation::class => $implementation,
        ]));

        self::assertSame($implementation, $containerResolver->optional(Implementation::class));
    }

    public function testOptionalThrowsInvalidExceptionWhenServiceDoesNotMatchServiceId(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer([
            Implementation::class => new OtherImplementation(),
        ]));

        $this->expectException(InvalidContainerServiceException::class);

        $containerResolver->optional(Implementation::class);
    }

    public function testOptionalAsReturnsTypedServiceWhenPresent(): void
    {
        $implementation    = new Implementation();
        $containerResolver = new ContainerResolver(new ArrayContainer([
            'app.service' => $implementation,
        ]));

        self::assertSame($implementation, $containerResolver->optionalAs('app.service', Contract::class));
    }

    public function testOptionalAsReturnsNullWhenServiceIsMissing(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer());

        self::assertNull($containerResolver->optionalAs('app.service', Contract::class));
    }

    public function testOptionalAsThrowsInvalidExceptionForWrongType(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer([
            'app.service' => new OtherImplementation(),
        ]));

        $this->expectException(InvalidContainerServiceException::class);

        $containerResolver->optionalAs('app.service', Contract::class);
    }

    public function testOptionalExistingReturnsDefaultWhenServiceIsMissing(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer());

        self::assertSame('default', $containerResolver->optionalExisting('missing', 'default'));
    }

    public function testOptionalExistingReturnsRawServiceWhenPresent(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer([
            'known' => 'value',
        ]));

        self::assertSame('value', $containerResolver->optionalExisting('known', 'default'));
    }

    public function testOptionalArrayReturnsEmptyArrayWhenServiceIsMissing(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer());

        self::assertSame([], $containerResolver->optionalArray());
    }

    public function testOptionalArrayReturnsArrayWhenServiceIsPresent(): void
    {
        $config = [
            'debug' => true,
        ];
        $containerResolver = new ContainerResolver(new ArrayContainer([
            'config' => $config,
        ]));

        self::assertSame($config, $containerResolver->optionalArray());
    }

    public function testOptionalArrayAllowsListValues(): void
    {
        $services          = ['first', 'second'];
        $containerResolver = new ContainerResolver(new ArrayContainer([
            'services' => $services,
        ]));

        self::assertSame($services, $containerResolver->optionalArray('services'));
    }

    public function testOptionalArrayThrowsInvalidExceptionWhenServiceIsNotArray(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer([
            'config' => 'invalid',
        ]));

        $this->expectException(InvalidContainerServiceException::class);
        $this->expectExceptionMessage('Container service "config" must be array; string given.');

        $containerResolver->optionalArray();
    }

    public function testOptionalMapReturnsEmptyMapWhenServiceIsMissing(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer());

        self::assertSame([], $containerResolver->optionalMap());
    }

    public function testOptionalMapReturnsStringKeyedMapWhenServiceIsPresent(): void
    {
        $config = [
            'debug' => true,
        ];
        $containerResolver = new ContainerResolver(new ArrayContainer([
            'config' => $config,
        ]));

        self::assertSame($config, $containerResolver->optionalMap());
    }

    public function testOptionalMapThrowsInvalidExceptionForNonStringKeys(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer([
            'config' => ['invalid'],
        ]));

        $this->expectException(InvalidContainerServiceException::class);
        $this->expectExceptionMessage('Container service "config" must be array with string keys; array given.');

        $containerResolver->optionalMap();
    }

    public function testContextIsExposedForConfigReaderReuse(): void
    {
        $containerResolver = ContainerResolver::forContext(new ArrayContainer(), 'context');

        self::assertSame('context', $containerResolver->context());
    }

    public function testExceptionMessagesDoNotDumpServiceValues(): void
    {
        $containerResolver = new ContainerResolver(new ArrayContainer([
            'config' => [
                'secret' => 'token',
            ],
        ]));

        try {
            $containerResolver->getAs('config', Contract::class);
            self::fail('Expected invalid service exception.');
        } catch (InvalidContainerServiceException $exception) {
            self::assertStringNotContainsString('secret', $exception->getMessage());
            self::assertStringNotContainsString('token', $exception->getMessage());
        }
    }
}
