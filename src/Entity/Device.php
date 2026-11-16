<?php

namespace App\Entity;

use App\Repository\DeviceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Boîtier embarqué. Il s'authentifie sur l'API avec un jeton dont seule l'empreinte est stockée.
 */
#[ORM\Entity(repositoryClass: DeviceRepository::class)]
class Device implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $serial;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\OneToOne(inversedBy: 'device')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Horse $horse = null;

    #[ORM\Column(nullable: true)]
    private ?int $batteryLevel = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $serial, string $plainToken)
    {
        $this->serial = $serial;
        $this->tokenHash = self::hashToken($plainToken);
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function generateSerial(): string
    {
        return 'EQT-'.strtoupper(bin2hex(random_bytes(3)));
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Le numéro doit être celui que le boîtier écrit dans la colonne horse_id de Supabase.
     */
    public function setSerial(string $serial): void
    {
        $this->serial = $serial;
    }

    public function rotateToken(string $plainToken): void
    {
        $this->tokenHash = self::hashToken($plainToken);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSerial(): string
    {
        return $this->serial;
    }

    public function getHorse(): ?Horse
    {
        return $this->horse;
    }

    public function setHorse(?Horse $horse): static
    {
        $this->horse = $horse;

        return $this;
    }

    public function getBatteryLevel(): ?int
    {
        return $this->batteryLevel;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function markSeen(\DateTimeImmutable $at, ?int $batteryLevel): void
    {
        $this->lastSeenAt = $at;
        if (null !== $batteryLevel) {
            $this->batteryLevel = $batteryLevel;
        }
    }

    /** Sans nouvelles depuis plus de 30 min (3 envois groupés manqués). */
    public function isSilent(): bool
    {
        return null === $this->lastSeenAt || $this->lastSeenAt < new \DateTimeImmutable('-30 minutes');
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUserIdentifier(): string
    {
        return $this->serial;
    }

    public function getRoles(): array
    {
        return ['ROLE_DEVICE'];
    }
}
