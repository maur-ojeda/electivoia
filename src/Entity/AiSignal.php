<?php

namespace App\Entity;

use App\Repository\AiSignalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiSignalRepository::class)]
#[ORM\Table(name: 'ai_signal')]
#[ORM\Index(columns: ['student_id'], name: 'idx_ai_signal_student')]
#[ORM\Index(columns: ['created_at'], name: 'idx_ai_signal_created_at')]
class AiSignal
{
    public const ACTION_VIEW = 'view';
    public const ACTION_CLICK = 'click';
    public const ACTION_ENROLL = 'enroll';
    public const ACTION_UNENROLL = 'unenroll';
    public const ACTION_PROFILE_UPDATE = 'profile_update';

    public const ALLOWED_ACTIONS = [
        self::ACTION_VIEW,
        self::ACTION_CLICK,
        self::ACTION_ENROLL,
        self::ACTION_UNENROLL,
        self::ACTION_PROFILE_UPDATE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'aiSignals')]
    private ?User $student = null;

    #[ORM\ManyToOne]
    private ?Course $course = null;

    #[ORM\Column(length: 50)]
    private ?string $action = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?School $school = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudent(): ?User
    {
        return $this->student;
    }

    public function setStudent(?User $student): static
    {
        $this->student = $student;

        return $this;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function setSchool(?School $school): static
    {
        $this->school = $school;

        return $this;
    }
}
