<?php

namespace App\Controller\Admin;

use App\Entity\InterestProfile;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class InterestProfileCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return InterestProfile::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            TextField::new('student.fullName', 'Alumno')
                ->onlyOnIndex(),
            TextField::new('student.rut', 'RUT')
                ->onlyOnIndex(),
            TextField::new('interestsBadge', 'Intereses')
                ->formatValue(function ($value) {
                    if ($value === 'sin-intereses') {
                        return '<span class="badge bg-secondary">Sin intereses</span>';
                    }
                    $items = explode(', ', $value);
                    $badges = [];
                    foreach ($items as $item) {
                        if (strpos($item, ':') === false) continue;
                        [$category, $score] = explode(':', $item, 2);
                        $color = match(true) {
                            $score >= 4 => 'success',
                            $score >= 2 => 'warning',
                            default => 'secondary',
                        };
                        $badges[] = sprintf('<span class="badge bg-%s me-1">%s: %s</span>', $color, htmlspecialchars($category), $score);
                    }
                    return implode(' ', $badges);
                })
                ->renderAsHtml(),
        ];
    }
}
