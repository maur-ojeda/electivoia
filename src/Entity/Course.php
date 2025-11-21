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

    /**
     * @var Collection<int, Attendance>
     */
    #[ORM\OneToMany(targetEntity: Attendance::class, mappedBy: 'course')]
    private Collection $attendances;

    #[ORM\ManyToOne(targetEntity: CourseCategory::class)]
    private ?CourseCategory $category = null;




    public function __construct()
    {
        // No se inicializa $teacher (es ManyToOne, no colección)
        $this->currentEnrollment = 0;
        $this->isActive = true;
        $this->enrollments = new ArrayCollection();
        $this->attendances = new ArrayCollection();
        $this->courseCategories = new ArrayCollection();
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

    /**
     * @return Collection<int, Attendance>
     */
    public function getAttendances(): Collection
    {
        return $this->attendances;
    }

    public function addAttendance(Attendance $attendance): static
    {
        if (!$this->attendances->contains($attendance)) {
            $this->attendances->add($attendance);
            $attendance->setCourse($this);
        }

        return $this;
    }

    public function removeAttendance(Attendance $attendance): static
    {
        if ($this->attendances->removeElement($attendance)) {
            // set the owning side to null (unless already changed)
            if ($attendance->getCourse() === $this) {
                $attendance->setCourse(null);
            }
        }

        return $this;
    }

    // Getter y setter
    public function getCategory(): ?CourseCategory
    {
        return $this->category;
    }

    public function setCategory(?CourseCategory $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? 'Curso sin nombre';
    }
}
