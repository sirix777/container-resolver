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
        $service = new Implementation();
        $resolver = new ContainerResolver(new ArrayContainer([
            Implementation::class => $service,
        ]));

        self::assertSame($service, $resolver->get(Implementation::class));
    }

    public function testGetThrowsMissingExceptionWhenServiceIsNotRegistered(): void
    {
        $resolver = ContainerResolver::forFactory(new ArrayContainer(), ExampleFactory::class);

        $this->expectException(MissingContainerServiceException::class);
        $this->expectExceptionMessage(sprintf(
            'Container service "%s" is required by %s but is not registered.',
            Implementation::class,
            ExampleFactory::class,
        ));

        $resolver->get(Implementation::class);
    }

    public function testGetWrapsNotFoundExceptionThrownByContainer(): void
    {
        $resolver = new ContainerResolver(new ArrayContainer(
            [Implementation::class => new Implementation()],
            [Implementation::class],
        ));

        try {
            $resolver->get(Implementation::class);
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
        $resolver = ContainerResolver::forFactory(new ArrayContainer([
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

        $resolver->get(Implementation::class);
    }

    public function testGetAsReturnsCustomServiceIdWithExpectedType(): void
    {
        $service = new Implementation();
        $resolver = new ContainerResolver(new ArrayContainer([
            'app.service' => $service,
        ]));

        self::assertSame($service, $resolver->getAs('app.service', Contract::class));
    }

    public function testGetAsThrowsInvalidExceptionForWrongType(): void
    {
        $resolver = new ContainerResolver(new ArrayContainer([
            'app.service' => new OtherImplementation(),
        ]));

        $this->expectException(InvalidContainerServiceException::class);
        $this->expectExceptionMessage(sprintf(
            'Container service "app.service" must be %s; %s given.',
            Contract::class,
            OtherImplementation::class,
        ));

        $resolver->getAs('app.service', Contract::class);
    }

    public function testGetExistingReturnsRawServiceValue(): void
    {
        $resolver = new ContainerResolver(new ArrayContainer([
            'scalar' => 'value',
        ]));

        self::assertSame('value', $resolver->getExisting('scalar'));
    }

    public function testGetExistingThrowsMissingExceptionForMissingService(): void
    {
        $resolver = new ContainerResolver(new ArrayContainer());

        $this->expectException(MissingContainerServiceException::class);

        $resolver->getExisting('missing');
    }

    public function testHasProxiesContainerHas(): void
    {
        $resolver = new ContainerResolver(new ArrayContainer([
            'known' => null,
        ]));

        self::assertTrue($resolver->has('known'));
        self::assertFalse($resolver->has('missing'));
    }

    public function testOptionalReturnsDefaultWhenServiceIsMissing(): void
    {
        $resolver = new ContainerResolver(new ArrayContainer());

        self::assertSame('default', $resolver->optional('missing', 'default'));
    }

    public function testOptionalReturnsServiceWhenPresent(): void
    {
        $resolver = new ContainerResolver(new ArrayContainer([
            'known' => 'value',
        ]));

        self::assertSame('value', $resolver->optional('known', 'default'));
    }

    public function testOptionalArrayReturnsEmptyArrayWhenServiceIsMissing(): void
    {
        $resolver = new ContainerResolver(new ArrayContainer());

        self::assertSame([], $resolver->optionalArray());
    }

    public function testOptionalArrayReturnsArrayWhenServiceIsPresent(): void
    {
        $config = ['debug' => true];
        $resolver = new ContainerResolver(new ArrayContainer([
            'config' => $config,
        ]));

        self::assertSame($config, $resolver->optionalArray());
    }

    public function testOptionalArrayThrowsInvalidExceptionWhenServiceIsNotArray(): void
    {
        $resolver = new ContainerResolver(new ArrayContainer([
            'config' => 'invalid',
        ]));

        $this->expectException(InvalidContainerServiceException::class);
        $this->expectExceptionMessage('Container service "config" must be array; string given.');

        $resolver->optionalArray();
    }

    public function testContextIsExposedForConfigReaderReuse(): void
    {
        $resolver = ContainerResolver::forContext(new ArrayContainer(), 'context');

        self::assertSame('context', $resolver->context());
    }

    public function testExceptionMessagesDoNotDumpServiceValues(): void
    {
        $resolver = new ContainerResolver(new ArrayContainer([
            'config' => ['secret' => 'token'],
        ]));

        try {
            $resolver->getAs('config', Contract::class);
            self::fail('Expected invalid service exception.');
        } catch (InvalidContainerServiceException $exception) {
            self::assertStringNotContainsString('secret', $exception->getMessage());
            self::assertStringNotContainsString('token', $exception->getMessage());
        }
    }
}
