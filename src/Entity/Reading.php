<?php

namespace App\Entity;

use App\Enum\Gait;
use App\Repository\ReadingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Résumé envoyé par le boîtier pour un intervalle (2 min par défaut).
 * Le brut haute fréquence reste sur la microSD du boîtier.
 */
#[ORM\Entity(repositoryClass: ReadingRepository::class)]
#[ORM\Index(name: 'reading_horse_time_idx', columns: ['horse_id', 'recorded_at'])]
class Reading
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Horse $horse;

    #[ORM\Column]
    private \DateTimeImmutable $recordedAt;

    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

    /** Température sous la couverture, °C (sonde SHT35 sur câble). */
    #[ORM\Column(nullable: true)]
    private ?float $temperature = null;

    /** Humidité relative sous la couverture, % (sonde SHT35 sur câble). */
    #[ORM\Column(nullable: true)]
    private ?float $humidity = null;

    /** Indice d'activité 0–100 calculé par le boîtier à partir de l'IMU. */
    #[ORM\Column(nullable: true)]
    private ?float $activity = null;

    #[ORM\Column(nullable: true, enumType: Gait::class)]
    private ?Gait $gait = null;

    #[ORM\Column]
    private int $rolls = 0;

    /** Pouls estimé (F10, expérimental). */
    #[ORM\Column(nullable: true)]
    private ?int $heartRate = null;

    #[ORM\Column(nullable: true)]
    private ?int $battery = null;

    public function __construct(Horse $horse, \DateTimeImmutable $recordedAt)
    {
        $this->horse = $horse;
        $this->recordedAt = $recordedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHorse(): Horse
    {
        return $this->horse;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function hasPosition(): bool
    {
        return null !== $this->latitude && null !== $this->longitude;
    }

    public function setPosition(?float $latitude, ?float $longitude): static
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;

        return $this;
    }

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function setTemperature(?float $temperature): static
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function getHumidity(): ?float
    {
        return $this->humidity;
    }

    public function setHumidity(?float $humidity): static
    {
        $this->humidity = $humidity;

        return $this;
    }

    public function getActivity(): ?float
    {
        return $this->activity;
    }

    public function setActivity(?float $activity): static
    {
        $this->activity = $activity;

        return $this;
    }

    public function getGait(): ?Gait
    {
        return $this->gait;
    }

    public function setGait(?Gait $gait): static
    {
        $this->gait = $gait;

        return $this;
    }

    public function getRolls(): int
    {
        return $this->rolls;
    }

    public function setRolls(int $rolls): static
    {
        $this->rolls = $rolls;

        return $this;
    }

    public function getHeartRate(): ?int
    {
        return $this->heartRate;
    }

    public function setHeartRate(?int $heartRate): static
    {
        $this->heartRate = $heartRate;

        return $this;
    }

    public function getBattery(): ?int
    {
        return $this->battery;
    }

    public function setBattery(?int $battery): static
    {
        $this->battery = $battery;

        return $this;
    }
}
