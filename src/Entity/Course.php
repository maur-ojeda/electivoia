<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $maxCapacity = null;

    #[ORM\Column]
    private int $currentEnrollment = 0;

    #[ORM\Column(length: 255)]
    private ?string $schedule = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\ManyToOne(inversedBy: 'coursesAsTeacher')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $teacher = null;

    /**
     * @var Collection<int, Enrollment>
     */
    #[ORM\OneToMany(targetEntity: Enrollment::class, mappedBy: 'course')]
    private Collection $enrollments;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $enrollmentDeadline = null;

    #[ORM\Column(nullable: true)]
    private ?array $targetGrades = null;


    public function __construct()
    {
        // No se inicializa $teacher (es ManyToOne, no colección)
        $this->currentEnrollment = 0;
        $this->isActive = true;
        $this->enrollments = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getMaxCapacity(): ?int
    {
        return $this->maxCapacity;
    }

    public function setMaxCapacity(int $maxCapacity): static
    {
        $this->maxCapacity = $maxCapacity;
        return $this;
    }

    public function getCurrentEnrollment(): int
    {
        return $this->currentEnrollment;
    }

    public function setCurrentEnrollment(int $currentEnrollment): static
    {
        $this->currentEnrollment = $currentEnrollment;
        return $this;
    }

    public function getSchedule(): ?string
    {
        return $this->schedule;
    }

    public function setSchedule(string $schedule): static
    {
        $this->schedule = $schedule;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getTeacher(): ?User
    {
        return $this->teacher;
    }

    public function setTeacher(?User $teacher): static
    {
        $this->teacher = $teacher;
        return $this;
    }

    /**
     * @return Collection<int, Enrollment>
     */
    public function getEnrollments(): Collection
    {
        return $this->enrollments;
    }

    public function addEnrollment(Enrollment $enrollment): static
    {
        if (!$this->enrollments->contains($enrollment)) {
            $this->enrollments->add($enrollment);
            $enrollment->setCourse($this);
        }

        return $this;
    }

    public function removeEnrollment(Enrollment $enrollment): static
    {
        if ($this->enrollments->removeElement($enrollment)) {
            // set the owning side to null (unless already changed)
            if ($enrollment->getCourse() === $this) {
                $enrollment->setCourse(null);
            }
        }

        return $this;
    }

    public function getEnrollmentDeadline(): ?\DateTimeImmutable
    {
        return $this->enrollmentDeadline;
    }

    public function setEnrollmentDeadline(?\DateTimeImmutable $enrollmentDeadline): static
    {
        $this->enrollmentDeadline = $enrollmentDeadline;

        return $this;
    }

    public function getTargetGrades(): ?array
    {
        return $this->targetGrades;
    }

    public function setTargetGrades(?array $targetGrades): static
    {
        $this->targetGrades = $targetGrades;

        return $this;
    }
}
