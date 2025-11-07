<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert; // Importar la validación

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_RUT', fields: ['rut'])] // Cambiar la restricción de unicidad al campo rut
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // #[ORM\Column(length: 180)] // Comentar o eliminar este campo
    // private ?string $email = null;

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

    /**
     * @var Collection<int, self>
     */
    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'guardians')]
    private Collection $guardianStudents;

    /**
     * @var Collection<int, self>
     */
    #[ORM\ManyToMany(targetEntity: self::class, mappedBy: 'guardianStudents')]
    private Collection $guardians;

    /**
     * @var Collection<int, Attendance>
     */
    #[ORM\OneToMany(targetEntity: Attendance::class, mappedBy: 'student')]
    private Collection $attendances;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fullName = null;

    #[Assert\Regex(
        pattern: '/^\d{7,8}-[\dKk]$/',
        message: 'El RUT debe tener el formato 12345678-9 o 12345678-K.'
    )]
    #[ORM\Column(length: 12, unique: true)] // Asegurar unicidad del RUT
    private ?string $rut = null;

    #[Assert\Choice(choices: ['M', 'F', 'Otro', 'Prefiero no decirlo'], message: 'El género debe ser M, F, Otro o Prefiero no decirlo.')]
    #[ORM\Column(length: 20, nullable: true)] // Puedes ajustar la longitud si es necesario
    private ?string $gender = null;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function __construct()
    {
        $this->coursesAsTeacher = new ArrayCollection();
        $this->enrollmentsAsStudent = new ArrayCollection();

        $this->guardianStudents = new ArrayCollection();
        $this->guardians = new ArrayCollection();
        $this->attendances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // public function getEmail(): ?string // Comentar o eliminar este método
    // {
    //     return $this->email;
    // }

    // public function setEmail(string $email): static // Comentar o eliminar este método
    // {
    //     $this->email = $email;

    //     return $this;
    // }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->rut; // Devolver el RUT como identificador
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        if (!$this->active) {
            return []; // Usuario desactivado no tiene roles
        }
        $roles = $this->roles;
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
        return $this->getRut() ?? 'Usuario sin RUT'; // Cambiar a RUT
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

    /**
     * @return Collection<int, self>
     */
    public function getGuardianStudents(): Collection
    {
        return $this->guardianStudents;
    }

    public function addGuardianStudent(self $guardianStudent): static
    {
        if (!$this->guardianStudents->contains($guardianStudent)) {
            $this->guardianStudents->add($guardianStudent);
        }

        return $this;
    }

    public function removeGuardianStudent(self $guardianStudent): static
    {
        $this->guardianStudents->removeElement($guardianStudent);

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getGuardians(): Collection
    {
        return $this->guardians;
    }

    public function addGuardian(self $guardian): static
    {
        if (!$this->guardians->contains($guardian)) {
            $this->guardians->add($guardian);
            $guardian->addGuardianStudent($this);
        }

        return $this;
    }

    public function removeGuardian(self $guardian): static
    {
        if ($this->guardians->removeElement($guardian)) {
            $guardian->removeGuardianStudent($this);
        }

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
            $attendance->setStudent($this);
        }

        return $this;
    }

    public function removeAttendance(Attendance $attendance): static
    {
        if ($this->attendances->removeElement($attendance)) {
            // set the owning side to null (unless already changed)
            if ($attendance->getStudent() === $this) {
                $attendance->setStudent(null);
            }
        }

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getRut(): ?string
    {
        return $this->rut;
    }

    public function setRut(string $rut): static
    {
        $this->rut = $rut;

        return $this;
    }

    // --- Métodos para el nuevo campo `gender` ---
    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    // --- Método para generar la URL del avatar ---
    public function getAvatarUrl(?int $size = 200): string
    {
        $genderParam = $this->gender ?? 'M'; // Usar 'M' como predeterminado si no hay género
        $identifier = $this->getRut() ?? $this->getId(); // Usar RUT o ID como identificador único

        // Codificar el identificador para evitar problemas con caracteres especiales
        $encodedIdentifier = urlencode($identifier);

        // Construir la URL del avatar
        $avatarUrl = "https://avatar.iran.liara.run/public/";

        // Ajustar la URL según el género
        switch (strtolower($genderParam)) {
            case 'f':
                $avatarUrl .= "girl/";
                break;
            case 'm':
                $avatarUrl .= "boy/";
                break;
            default:
                $avatarUrl .= "girl/"; // O 'boy/' o un avatar neutro si es 'Otro' o 'Prefiero no decirlo'
                break;
        }

        $avatarUrl .= "?username={$encodedIdentifier}";

        if ($size) {
            $avatarUrl .= "&size={$size}";
        }

        return $avatarUrl;
    }
    // --- Fin del método de avatar ---
}
