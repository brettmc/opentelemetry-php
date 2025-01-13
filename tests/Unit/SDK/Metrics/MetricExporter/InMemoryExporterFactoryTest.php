<?php

declare(strict_types=1);

namespace OpenTelemetry\Tests\Unit\SDK\Metrics\MetricExporter;

use OpenTelemetry\SDK\Metrics\MetricExporter\InMemoryExporterFactory;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryExporterFactory::class)]
class InMemoryExporterFactoryTest extends TestCase
{
    public function test_create(): void
    {
        $exporter = (new InMemoryExporterFactory())->create();
        $this->assertInstanceOf(MetricExporterInterface::class, $exporter);
    }

    public function test_type(): void
    {
        $factory = new InMemoryExporterFactory();
        $this->assertSame('memory', $factory->type());
    }

    public function test_priority(): void
    {
        $factory = new InMemoryExporterFactory();
        $this->assertSame(0, $factory->priority());
    }
}
