<?php

namespace App\Api;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Lot de mesures envoyé toutes les 10 minutes, ou immédiatement en cas d'événement (F5).
 */
final readonly class ReadingBatch
{
    /**
     * @param list<ReadingInput> $readings
     */
    public function __construct(
        #[Assert\Count(min: 1, max: 100)]
        #[Assert\Valid]
        public array $readings,
    ) {
    }
}
