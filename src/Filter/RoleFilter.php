<?php

namespace App\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class RoleFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ChoiceType::class)
            ->setFormTypeOptions([
                'choices' => [
                    'Administrador' => 'ROLE_ADMIN',
                    'Profesor' => 'ROLE_TEACHER',
                    'Estudiante' => 'ROLE_STUDENT',
                    'Apoderado' => 'ROLE_GUARDIAN',
                ],
                'placeholder' => 'Seleccionar rol...',
            ]);
    }

    public function apply(
        QueryBuilder $queryBuilder,
        FilterDataDto $filterDataDto,
        ?FieldDto $fieldDto,
        EntityDto $entityDto
    ): void {
        $value = $filterDataDto->getValue();
        
        if (empty($value)) {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        $property = $filterDataDto->getProperty();
        $parameterName = $filterDataDto->getParameterName();

        // Para PostgreSQL con campo JSON
        // Usamos la función DQL personalizada registrada
        $queryBuilder->andWhere(
            $queryBuilder->expr()->eq(
                sprintf('json_array_contains(%s.%s, :%s)', $alias, $property, $parameterName),
                'true'
            )
        )->setParameter($parameterName, $value);
    }
}
