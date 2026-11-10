<?php

namespace App\Api;

use App\Enum\Gait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une mesure résumée telle qu'envoyée par le boîtier.
 */
final readonly class ReadingInput
{
    public function __construct(
        #[Assert\LessThanOrEqual('+5 minutes', message: 'La mesure est datée dans le futur.')]
        public \DateTimeImmutable $recordedAt,
        #[Assert\Range(min: -90, max: 90)]
        public ?float $latitude = null,
        #[Assert\Range(min: -180, max: 180)]
        public ?float $longitude = null,
        #[Assert\Range(min: -40, max: 70)]
        public ?float $temperature = null,
        #[Assert\Range(min: 0, max: 100)]
        public ?float $humidity = null,
        #[Assert\Range(min: -40, max: 60)]
        public ?float $outsideTemperature = null,
        #[Assert\Range(min: 0, max: 100)]
        public ?float $activity = null,
        public ?Gait $gait = null,
        #[Assert\Range(min: 0, max: 50)]
        public int $rolls = 0,
        #[Assert\Range(min: 15, max: 250)]
        public ?int $heartRate = null,
        #[Assert\Range(min: 0, max: 100)]
        public ?int $battery = null,
    ) {
    }
}
