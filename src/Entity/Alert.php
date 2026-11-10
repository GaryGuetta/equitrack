<?php

namespace App\Entity;

use App\Enum\AlertSeverity;
use App\Enum\AlertType;
use App\Repository\AlertRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlertRepository::class)]
#[ORM\Index(name: 'alert_horse_open_idx', columns: ['horse_id', 'acknowledged_at'])]
class Alert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Horse $horse;

    #[ORM\Column(enumType: AlertType::class)]
    private AlertType $type;

    #[ORM\Column(enumType: AlertSeverity::class)]
    private AlertSeverity $severity;

    #[ORM\Column(length: 255)]
    private string $message;

    /** Moment de la mesure qui a déclenché l'alerte. */
    #[ORM\Column]
    private \DateTimeImmutable $triggeredAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    public function __construct(Horse $horse, AlertType $type, string $message, \DateTimeImmutable $triggeredAt)
    {
        $this->horse = $horse;
        $this->type = $type;
        $this->severity = $type->severity();
        $this->message = $message;
        $this->triggeredAt = $triggeredAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHorse(): Horse
    {
        return $this->horse;
    }

    public function getType(): AlertType
    {
        return $this->type;
    }

    public function getSeverity(): AlertSeverity
    {
        return $this->severity;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getTriggeredAt(): \DateTimeImmutable
    {
        return $this->triggeredAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAcknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    public function isOpen(): bool
    {
        return null === $this->acknowledgedAt;
    }

    public function acknowledge(): void
    {
        $this->acknowledgedAt ??= new \DateTimeImmutable();
    }
}
