<?php

namespace App\Entity;

use App\Enum\BlanketWeight;
use App\Enum\Clip;
use App\Repository\HorseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HorseRepository::class)]
class Horse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private ?string $name = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $breed = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1980, max: 2030)]
    private ?int $birthYear = null;

    #[ORM\Column(enumType: Clip::class)]
    private Clip $clip = Clip::None;

    /** Cheval frileux, âgé ou maigre : besoin d'un cran de couverture en plus. */
    #[ORM\Column]
    private bool $coldSensitive = false;

    /** Abri disponible au pré : on tolère un cran de moins par temps sec. */
    #[ORM\Column]
    private bool $hasShelter = false;

    #[ORM\Column(enumType: BlanketWeight::class)]
    private BlanketWeight $currentBlanket = BlanketWeight::None;

    /**
     * Polygone de l'enclos, liste de points [latitude, longitude] (F4).
     *
     * @var list<array{0: float, 1: float}>
     */
    #[ORM\Column(type: 'json')]
    #[Assert\When(
        expression: 'value != []',
        constraints: [new Assert\Count(min: 3, minMessage: 'L\'enclos doit compter au moins 3 points.')],
    )]
    private array $paddock = [];

    /** Fichier de la photo dans public/uploads/chevaux, déjà réduite (voir HorsePhotoStorage). */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $photo = null;

    #[ORM\ManyToOne(inversedBy: 'horses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\OneToOne(mappedBy: 'horse')]
    private ?Device $device = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getBreed(): ?string
    {
        return $this->breed;
    }

    public function setBreed(?string $breed): static
    {
        $this->breed = $breed;

        return $this;
    }

    public function getBirthYear(): ?int
    {
        return $this->birthYear;
    }

    public function setBirthYear(?int $birthYear): static
    {
        $this->birthYear = $birthYear;

        return $this;
    }

    public function getAge(): ?int
    {
        return null === $this->birthYear ? null : (int) date('Y') - $this->birthYear;
    }

    public function getClip(): Clip
    {
        return $this->clip;
    }

    public function setClip(Clip $clip): static
    {
        $this->clip = $clip;

        return $this;
    }

    public function isColdSensitive(): bool
    {
        return $this->coldSensitive;
    }

    public function setColdSensitive(bool $coldSensitive): static
    {
        $this->coldSensitive = $coldSensitive;

        return $this;
    }

    public function hasShelter(): bool
    {
        return $this->hasShelter;
    }

    public function setHasShelter(bool $hasShelter): static
    {
        $this->hasShelter = $hasShelter;

        return $this;
    }

    public function getCurrentBlanket(): BlanketWeight
    {
        return $this->currentBlanket;
    }

    public function setCurrentBlanket(BlanketWeight $currentBlanket): static
    {
        $this->currentBlanket = $currentBlanket;

        return $this;
    }

    /** @return list<array{0: float, 1: float}> */
    public function getPaddock(): array
    {
        return $this->paddock;
    }

    /** @param list<array{0: float, 1: float}> $paddock */
    public function setPaddock(array $paddock): static
    {
        $this->paddock = $paddock;

        return $this;
    }

    public function hasPaddock(): bool
    {
        return \count($this->paddock) >= 3;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getDevice(): ?Device
    {
        return $this->device;
    }

    public function setDevice(?Device $device): static
    {
        if (null !== $device && $device->getHorse() !== $this) {
            $device->setHorse($this);
        }
        $this->device = $device;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }
}
