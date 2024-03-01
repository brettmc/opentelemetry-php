<?php

declare(strict_types=1);

namespace OpenTelemetry\API\Baggage;

final class Entry
{
    public function __construct(
        private readonly mixed $value,
        private readonly MetadataInterface $metadata
    ) {
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getMetadata(): MetadataInterface
    {
        return $this->metadata;
    }
}
