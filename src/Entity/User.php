<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * @var Collection<int, Course>
     * Relación: Un profesor tiene muchos cursos
     */
    #[ORM\OneToMany(targetEntity: Course::class, mappedBy: 'teacher')]
    private Collection $coursesAsTeacher;

    /**
     * @var Collection<int, Enrollment>
     * Relación: Un estudiante tiene muchas inscripciones
     * NOTA: Esta propiedad fue la elegida para mantener la relación con Enrollment,
     * eliminando la duplicada '$enrollments'.
     */
    #[ORM\OneToMany(targetEntity: Enrollment::class, mappedBy: 'student')]
    private Collection $enrollmentsAsStudent;

    /**
     * @var ?InterestProfile
     * Relación: Un estudiante tiene un perfil de intereses (uno a uno)
     */
    #[ORM\OneToOne(mappedBy: 'student', targetEntity: InterestProfile::class, cascade: ['persist', 'remove'])]
    private ?InterestProfile $interestProfile = null;

    #[ORM\Column(nullable: true)]
    private ?float $averageGrade = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $grade = null;

    // Se eliminó la propiedad Collection $enrollments duplicada y conflictiva.

    public function __construct()
    {
        $this->coursesAsTeacher = new ArrayCollection();
        $this->enrollmentsAsStudent = new ArrayCollection();
        // Se eliminó la inicialización de $this->enrollments
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles ?: ['ROLE_USER'];
        return $this;
    }






    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    // --- Relación con Course (profesor) ---

    /**
     * @return Collection<int, Course>
     */
    public function getCoursesAsTeacher(): Collection
    {
        return $this->coursesAsTeacher;
    }

    public function addCourseAsTeacher(Course $course): static
    {
        if (!$this->coursesAsTeacher->contains($course)) {
            $this->coursesAsTeacher->add($course);
            $course->setTeacher($this); // Establece el profesor en el curso
        }

        return $this;
    }

    public function removeCourseAsTeacher(Course $course): static
    {
        if ($this->coursesAsTeacher->removeElement($course)) {
            // Si el curso aún apunta a este usuario como profesor, lo reseteamos
            if ($course->getTeacher() === $this) {
                $course->setTeacher(null);
            }
        }

        return $this;
    }

    // --- Relación con Enrollment (estudiante) ---

    /**
     * @return Collection<int, Enrollment>
     */
    public function getEnrollmentsAsStudent(): Collection
    {
        return $this->enrollmentsAsStudent;
    }

    public function addEnrollmentAsStudent(Enrollment $enrollment): static
    {
        if (!$this->enrollmentsAsStudent->contains($enrollment)) {
            $this->enrollmentsAsStudent->add($enrollment);
            $enrollment->setStudent($this);
        }

        return $this;
    }

    public function removeEnrollmentAsStudent(Enrollment $enrollment): static
    {
        if ($this->enrollmentsAsStudent->removeElement($enrollment)) {
            // Si la inscripción aún apunta a este usuario como estudiante, lo reseteamos
            if ($enrollment->getStudent() === $this) {
                $enrollment->setStudent(null);
            }
        }

        return $this;
    }

    // --- Relación con InterestProfile ---

    public function getInterestProfile(): ?InterestProfile
    {
        return $this->interestProfile;
    }

    public function setInterestProfile(?InterestProfile $interestProfile): static
    {
        // Rompe relación inversa si ya existía
        if ($this->interestProfile) {
            $this->interestProfile->setStudent(null);
        }

        $this->interestProfile = $interestProfile;

        // Establece relación inversa
        if ($interestProfile) {
            $interestProfile->setStudent($this);
        }

        return $this;
    }

    // Se eliminaron los métodos getEnrollments(), addEnrollment() y removeEnrollment() duplicados.

    public function getAverageGrade(): ?float
    {
        return $this->averageGrade;
    }

    public function setAverageGrade(?float $averageGrade): static
    {
        $this->averageGrade = $averageGrade;

        return $this;
    }

    public function __toString(): string
    {
        return $this->getEmail() ?? 'Usuario sin email';
    }

    public function getGrade(): ?string
    {
        return $this->grade;
    }

    public function setGrade(?string $grade): static
    {
        $this->grade = $grade;

        return $this;
    }
}
