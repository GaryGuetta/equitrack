<?php

namespace App\Advice;

use App\Enum\BlanketWeight;

final readonly class BlanketAdvice
{
    /**
     * @param list<string> $reasons explications affichées au propriétaire
     */
    public function __construct(
        public BlanketWeight $recommended,
        public BlanketWeight $current,
        public float $referenceTemperature,
        public string $temperatureSource,
        public array $reasons,
    ) {
    }

    /** @return 'ok'|'lighter'|'warmer' */
    public function verdict(): string
    {
        return match ($this->recommended->rank() <=> $this->current->rank()) {
            0 => 'ok',
            -1 => 'lighter',
            1 => 'warmer',
        };
    }
}
