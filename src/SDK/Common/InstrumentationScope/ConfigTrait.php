<?php

declare(strict_types=1);

namespace OpenTelemetry\SDK\Common\InstrumentationScope;

trait ConfigTrait
{
    public function __construct(private bool $disabled = false)
    {
    }

    public function setDisabled(bool $disabled): void
    {
        $this->disabled = $disabled;
    }

    public function isEnabled(): bool
    {
        return $this->disabled === false;
    }
}
