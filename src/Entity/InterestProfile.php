<?php

namespace App\Entity;

use App\Repository\InterestProfileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InterestProfileRepository::class)]
class InterestProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var ?User
     * Relación OneToOne: El perfil de interés pertenece a un estudiante.
     * Este es el LADO PROPIETARIO, por lo que lleva inversedBy y JoinColumn.
     * El valor 'interestProfile' debe coincidir con el nombre de la propiedad en User.php.
     */
    #[ORM\OneToOne(inversedBy: 'interestProfile', targetEntity: User::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $student = null;

    // Almacenar intereses como JSON
    #[ORM\Column(type: 'json')]
    private array $interests = [];

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

    public function getInterests(): array
    {
        return $this->interests;
    }

    public function setInterests(array $interests): static
    {
        $this->interests = $interests;

        return $this;
    }
}
