<?php

namespace App\Controller\Admin;

use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\HttpFoundation\RequestStack;

class CourseCrudController extends AbstractCrudController
{
    // Definimos las opciones de grados de educación chilena
    private const CHILEAN_GRADES = [
        // Educación Básica
        '1° Básico' => '1B',
        '2° Básico' => '2B',
        '3° Básico' => '3B',
        '4° Básico' => '4B',
        '5° Básico' => '5B',
        '6° Básico' => '6B',
        '7° Básico' => '7B',
        '8° Básico' => '8B',
        // Educación Media
        '1° Medio' => '1M',
        '2° Medio' => '2M',
        '3° Medio' => '3M',
        '4° Medio' => '4M',
    ];

    // Días de la semana para el horario
    private const WEEKDAYS = [
        'Lunes' => 'Lunes',
        'Martes' => 'Martes',
        'Miércoles' => 'Miércoles',
        'Jueves' => 'Jueves',
        'Viernes' => 'Viernes',
    ];

    public function __construct(
        private RequestStack $requestStack
    ) {}

    public static function getEntityFqcn(): string
    {
        return Course::class;
    }

    /**
     * Helper para manejar la conversión del horario de campos separados al string de la entidad
     * 💡 CORRECCIÓN: Se elimina $crudFormName y se detecta el array de datos directamente.
     */
    private function handleScheduleConversion(Course $course): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request && in_array($request->getMethod(), ['POST', 'PUT'])) {
            $formData = $request->request->all();

            // Heurística para encontrar el array que contiene los datos del formulario,
            // ya que no podemos usar getContext()->getForm()->getName().
            $formValues = null;
            foreach ($formData as $key => $value) {
                // Buscamos el array que contiene los campos no mapeados (scheduleDay/Time)
                if (is_array($value) && array_key_exists('scheduleDay', $value)) {
                    $formValues = $value;
                    break;
                }
            }

            if (!$formValues) {
                return;
            }

            $day = $formValues['scheduleDay'] ?? '';
            $time = $formValues['scheduleTime'] ?? '';

            if ($day && $time) {
                // Formato final: "Lunes 14:00 - 15:00"
                $course->setSchedule(sprintf('%s %s', $day, $time));
            } elseif ($time && !$day) {
                // Si solo hay tiempo, lo guardamos tal cual 
                $course->setSchedule($time);
            } else {
                // Si ambos están vacíos, limpia el horario
                $course->setSchedule(null);
            }
        }
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entity): void
    {
        if ($entity instanceof Course) {
            // 💡 CORRECCIÓN: Se llama a la función sin el argumento getForm() problemático
            $this->handleScheduleConversion($entity);
        }
        parent::persistEntity($entityManager, $entity);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entity): void
    {
        if ($entity instanceof Course) {
            // 💡 CORRECCIÓN: Se llama a la función sin el argumento getForm() problemático
            $this->handleScheduleConversion($entity);
        }
        parent::updateEntity($entityManager, $entity);
    }

    public function configureFields(string $pageName): iterable
    {
        $course = $this->getContext()?->getEntity()?->getInstance();
        $currentSchedule = $course?->getSchedule();

        // Intentar separar el día y la hora para la edición
        $scheduleDay = null;
        $scheduleTime = $currentSchedule;

        if ($currentSchedule) {
            // Buscamos si el horario comienza con alguno de los días de la semana
            foreach (self::WEEKDAYS as $dayName => $dayValue) {
                if (str_starts_with($currentSchedule, $dayName)) {
                    $scheduleDay = $dayValue;
                    // El tiempo es el resto del string después del día y un espacio
                    $scheduleTime = trim(substr($currentSchedule, strlen($dayName)));
                    break;
                }
            }
        }

        return [
            TextField::new('name', 'Nombre'),
            TextEditorField::new('description', 'Descripción')->onlyOnForms(),
            IntegerField::new('maxCapacity', 'Cupo máximo'),
            // --- Campos para el HORARIO (solo en formularios) ---
            ChoiceField::new('scheduleDay', 'Día')
                ->setChoices(self::WEEKDAYS)
                ->setFormTypeOptions([
                    'mapped' => false, // No mapear a la entidad
                    'data' => $scheduleDay, // Cargar el valor en edición
                ])
                ->onlyOnForms(),

            TextField::new('scheduleTime', 'Horario (ej: 14:00 - 15:00)')
                ->setHelp('Franja horaria. Rango sugerido: 08:00 a 20:00')
                ->setFormTypeOptions([
                    'mapped' => false, // No mapear a la entidad
                    'data' => $scheduleTime, // Cargar el valor en edición
                ])
                ->onlyOnForms(),

            // Campo 'schedule' original solo para la vista de índice
            TextField::new('schedule', 'Horario')->onlyOnIndex(),
            // --- Fin de campos de HORARIO ---

            DateTimeField::new('enrollmentDeadline', 'Fecha límite de inscripción')
                ->setHelp('Dejar vacío para permitir inscripciones sin límite de tiempo'),
            BooleanField::new('isActive', 'Activo'),
            AssociationField::new('teacher', 'Profesor')
                ->setCrudController(UserCrudController::class),

            ChoiceField::new('targetGrades', 'Cursos Destinados')
                ->setChoices(self::CHILEAN_GRADES)
                ->allowMultipleChoices(true)
                ->renderAsBadges(),

            /*TextField::new('targetGradesDisplay', 'Grados')
                ->setLabel('Grados')
                ->formatValue(function ($value, Course $course) {
                    $grades = $course->getTargetGrades();
                    $displayGrades = array_map(fn($code) => array_search($code, self::CHILEAN_GRADES) ?: $code, is_array($grades) ? $grades : []);
                    return implode(', ', $displayGrades);
                })
                ->onlyOnIndex(),
*/

        ];
    }
}
