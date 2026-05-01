<?php

namespace App\Entity;

use App\Repository\SchoolRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SchoolRepository::class)]
class School
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 100, unique: true)]
    private string $slug;

    /** Rol Base de Datos — código único del colegio en Chile */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $rbd = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    /** free | basic | premium */
    #[ORM\Column(length: 20, options: ['default' => 'free'])]
    private string $plan = 'free';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $enrollmentStart = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $enrollmentEnd = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getRbd(): ?string { return $this->rbd; }
    public function setRbd(?string $rbd): static { $this->rbd = $rbd; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }

    public function getPlan(): string { return $this->plan; }
    public function setPlan(string $plan): static { $this->plan = $plan; return $this; }

    public function getEnrollmentStart(): ?\DateTimeImmutable { return $this->enrollmentStart; }
    public function setEnrollmentStart(?\DateTimeImmutable $enrollmentStart): static { $this->enrollmentStart = $enrollmentStart; return $this; }

    public function getEnrollmentEnd(): ?\DateTimeImmutable { return $this->enrollmentEnd; }
    public function setEnrollmentEnd(?\DateTimeImmutable $enrollmentEnd): static { $this->enrollmentEnd = $enrollmentEnd; return $this; }

    /** Returns true when the global enrollment window is currently open (or not configured). */
    public function isEnrollmentOpen(): bool
    {
        $now = new \DateTimeImmutable();
        if ($this->enrollmentStart !== null && $now < $this->enrollmentStart) {
            return false;
        }
        if ($this->enrollmentEnd !== null && $now > $this->enrollmentEnd) {
            return false;
        }
        return true;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function __toString(): string { return $this->name; }
}
