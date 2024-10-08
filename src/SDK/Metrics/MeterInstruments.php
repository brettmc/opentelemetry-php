<?php

declare(strict_types=1);

namespace OpenTelemetry\SDK\Metrics;

/**
 * @internal
 */
final class MeterInstruments
{
    public ?int $startTimestamp = null;
    /**
     * @var array<string, array<string, array{Instrument, StalenessHandlerInterface&ReferenceCounterInterface, RegisteredInstrument}>>
     */
    public array $observers = [];
    /**
     * @var array<string, array<string, array{Instrument, StalenessHandlerInterface&ReferenceCounterInterface, RegisteredInstrument}>>
     */
    public array $writers = [];
}
