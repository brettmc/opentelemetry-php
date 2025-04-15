<?php

declare(strict_types=1);

namespace OpenTelemetry\Tests\Integration\Config;

use OpenTelemetry\Config\SDK\ComponentProvider\OutputStreamParser;
use OpenTelemetry\Config\SDK\Configuration;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ConfigurationTest extends TestCase
{
    public function setUp(): void
    {
        // set up mock file system with /var/log directory, for otlp_file exporter.
        $root = vfsStream::setup('/', null, ['var' => ['log' => []]])->url();

        OutputStreamParser::setRoot($root);
    }

    public function tearDown(): void
    {
        OutputStreamParser::reset();
    }

    #[DataProvider('openTelemetryConfigurationDataProvider')]
    public function test_open_telemetry_configuration(string $file): void
    {
        $this->expectNotToPerformAssertions();
        Configuration::parseFile($file)->create();
    }

    public static function openTelemetryConfigurationDataProvider(): iterable
    {
        yield 'kitchen-sink' => [__DIR__ . '/configurations/kitchen-sink.yaml'];
        yield 'anchors' => [__DIR__ . '/configurations/anchors.yaml'];
    }

    public function test_configurators(): void
    {
        $sdk = Configuration::parseFile(__DIR__ . '/configurations/configurators.yaml')->create()->build();
        $tracer_a = $sdk->getTracerProvider()->getTracer('A.foo');
        $tracer_b = $sdk->getTracerProvider()->getTracer('B.foo');
        $tracer_c = $sdk->getTracerProvider()->getTracer('C.foo');

        $this->assertTrue($tracer_a->isEnabled(), 'enabled by configurator');
        $this->assertFalse($tracer_b->isEnabled(), 'disabled by configurator');
        $this->assertFalse($tracer_c->isEnabled(), 'default disabled');

        $logger_a = $sdk->getLoggerProvider()->getLogger('A.foo');
        $logger_b = $sdk->getLoggerProvider()->getLogger('B.foo');
        $logger_c = $sdk->getLoggerProvider()->getLogger('C.foo');

        $this->assertTrue($logger_a->isEnabled(), 'enabled by configurator');
        $this->assertFalse($logger_b->isEnabled(), 'disabled by configurator');
        $this->assertFalse($logger_c->isEnabled(), 'default disabled');

        $meter_a = $sdk->getMeterProvider()->getMeter('A.foo');
        $meter_b = $sdk->getMeterProvider()->getMeter('B.foo');
        $meter_c = $sdk->getMeterProvider()->getMeter('C.foo');

        $this->assertTrue($meter_a->createCounter('cnt')->isEnabled(), 'enabled by configurator');
        $this->assertFalse($meter_b->createCounter('cnt')->isEnabled(), 'disabled by configurator');
        $this->assertFalse($meter_c->createCounter('cnt')->isEnabled(), 'default disabled');
    }

    public function test_resource(): void
    {
        $expectedKeys = [
            'host.name',
            'host.arch',
            'os.type',
            'os.description',
            'os.name',
            'os.version',
            'process.pid',
            'process.executable.path',
            'process.command',
            'process.owner',
            'service.name',
            'service.namespace',
            'service.version',
            'string_key',
            'int_key',
            'bool_key',
            'double_key',
            'string_array_key',
            'int_array_key',
            'bool_array_key',
            'double_array_key',
        ];

        $removedKeys = [
            'process.command_args',
        ];

        $sdk = Configuration::parseFile(__DIR__ . '/configurations/resource.yaml')->create()->build();
        $tracer = $sdk->getTracerProvider()->getTracer('test');

        $tracerReflection = new \ReflectionClass($tracer);
        $sharedStateProperty = $tracerReflection->getProperty('tracerSharedState');
        $sharedStateProperty->setAccessible(true);
        $sharedState = $sharedStateProperty->getValue($tracer);

        $stateReflection = new \ReflectionClass($sharedState);
        $resourceProperty = $stateReflection->getProperty('resource');
        $resourceProperty->setAccessible(true);
        $resource = $resourceProperty->getValue($sharedState);

        $this->assertInstanceOf(ResourceInfo::class, $resource);
        $this->assertSame('https://opentelemetry.io/schemas/1.30.0', $resource->getSchemaUrl());
        $attributes = $resource->getAttributes()->toArray();

        foreach ($expectedKeys as $k) {
            $this->assertArrayHasKey($k, $attributes);
        }

        foreach ($removedKeys as $k) {
            $this->assertArrayNotHasKey($k, $attributes);
        }
    }

    public function test_resource_include_exclude(): void
    {
        $expectedKeys = [
            'process.pid',
            'process.executable.path',
            'process.owner',
            'host.arch',
        ];

        $sdk = Configuration::parseFile(__DIR__ . '/configurations/resource-include-exclude.yaml')->create()->build();
        $tracer = $sdk->getTracerProvider()->getTracer('test');

        $tracerReflection = new \ReflectionClass($tracer);
        $sharedStateProperty = $tracerReflection->getProperty('tracerSharedState');
        $sharedStateProperty->setAccessible(true);
        $sharedState = $sharedStateProperty->getValue($tracer);

        $stateReflection = new \ReflectionClass($sharedState);
        $resourceProperty = $stateReflection->getProperty('resource');
        $resourceProperty->setAccessible(true);
        $resource = $resourceProperty->getValue($sharedState);

        $attributes = $resource->getAttributes()->toArray();

        foreach ($expectedKeys as $k) {
            $this->assertArrayHasKey($k, $attributes);
        }
        /** @psalm-suppress PossiblyInvalidArgument */
        $this->assertCount(4, $attributes);
    }

    #[DoesNotPerformAssertions]
    public function test_minimal(): void
    {
        Configuration::parseFile(__DIR__ . '/configurations/minimal.yaml')->create()->build();
    }
}
