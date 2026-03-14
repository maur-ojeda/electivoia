<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use Symfony\Component\Validator\Constraints\Regex;


class UserCrudController extends AbstractCrudController
{
    private const CHILEAN_GRADES = [
        '3° Medio' => '3M',
        '4° Medio' => '4M',
    ];

    // Longitud de la contraseña temporal (ya no se usa para generación aleatoria)
    // private const TEMPORARY_PASSWORD_LENGTH = 16; // Comentamos o eliminamos esta constante

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private RequestStack $requestStack // 💡 FIX: Inyectamos RequestStack para acceder al formulario
    ) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Usuario')
            ->setEntityLabelInPlural('Usuarios')
            ->setSearchFields(['fullName', 'rut'])
            ->setDefaultSort(['fullName' => 'ASC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('fullName')
            ->add('rut')
            ->add('grade')
            ->add('gender')
            ->add('active')
            ->add(\App\Filter\RoleFilter::new('roles', 'Rol'));
    }



    // 1. GENERAR CONTRASEÑA TEMPORAL (ahora basada en RUT) Y ASIGNARLA A LA PROPIEDAD NO MAPEADA 'plainPassword'.
    public function createEntity(string $entityFqcn): User
    {
        /** @var User $user */
        $user = new $entityFqcn();

        // --- Nueva Lógica: Contraseña basada en RUT ---
        $rut = $user->getRut();
        if ($rut) {
            // Extraer solo los dígitos antes del guión
            $digits = preg_replace('/[^0-9]/', '', $rut);
            // Tomar los primeros 6 dígitos
            $plainPassword = substr($digits, 0, 6);
        } else {
            // Fallback: Si no hay RUT (aunque debería haberlo en este punto si se completa el formulario),
            // generamos una contraseña temporal aleatoria como antes.
            // Opcional: Lanzar una excepción o manejar este caso de otra manera.
            // Para este ejemplo, generamos una corta y clara.
            $plainPassword = 'rut_temp'; // Esto se sobrescribirá si se ingresa RUT y se envía de nuevo.
        }
        // --- Fin Nueva Lógica ---

        // Establecer la contraseña en la propiedad 'plainPassword' del objeto User
        $user->plainPassword = $plainPassword;

        return $user;
    }

    public function configureFields(string $pageName): iterable
    {
        // Obtener la entidad actual para lógica condicional
        $currentUser = $this->getContext()?->getEntity()?->getInstance();
        $isNewPage = $pageName === Crud::PAGE_NEW;

        $fields = [
            IdField::new('id')->onlyOnIndex(),

            // Nombre completo (obligatorio para todos)
            TextField::new('fullName', 'Nombre completo')
                ->setRequired(true),

            // RUT (obligatorio para todos, único)
            $rutField = TextField::new('rut', 'RUT')
                ->setHelp('Formato chileno: 12345678-9')
                ->setRequired(true)
                ->setFormTypeOption('constraints', [
                    new Regex([
                        'pattern' => '/^\d{7,8}-[\dKk]$/',
                        'message' => 'El RUT debe tener el formato 12345678-9 o 12345678-K.'
                    ])
                ]),

            ChoiceField::new('gender', 'Género')->setChoices([
                'Masculino' => 'M',
                'Femenino' => 'F',
                'Otro' => 'Otro',
                'Prefiero no decirlo' => 'Prefiero no decirlo',
            ])->allowMultipleChoices(false)->renderAsBadges(),


            EmailField::new('email', 'Email')
                ->setRequired(false)
                ->setHelp('Opcional. Se usará para notificaciones de inscripción.'),

            ChoiceField::new('grade', 'Curso/Grado')
                ->setChoices(self::CHILEAN_GRADES)
                ->allowMultipleChoices(false)
                ->renderAsNativeWidget(), // Muestra como <select> estándar
            
            NumberField::new('averageGrade', 'Promedio General')
                ->setNumDecimals(1)
                ->setHelp('Promedio de notas del estudiante (escala 1.0 - 7.0)'),
            
            BooleanField::new('active', 'Activo'), // ← Campo para desactivar

        ];

        // --- Campo de Contraseña ---
        $passwordField = TextField::new('plainPassword', 'Contraseña (Defecto si no se cambia)')
            ->setFormTypeOptions([
                'mapped' => false,
                'required' => false,
            ])
            ->onlyOnForms();

        if ($isNewPage) {
            $generatedPassword = '';
            if ($currentUser && isset($currentUser->plainPassword)) {
                $generatedPassword = $currentUser->plainPassword;
            }

            // Mostramos la contraseña generada en el mensaje de ayuda.
            $passwordField
                ->setFormTypeOption('attr', [
                    'type' => 'text',
                    'readonly' => 'readonly',
                    'style' => 'font-family: monospace; font-size: 1.2em; background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px; padding: 8px 12px;' // Estilo para resaltar la contraseña
                ])
                // Actualizamos el mensaje para reflejar la nueva lógica
                ->setHelp("Contraseña generada (primeros 6 dígitos del RUT): <code style='font-family: monospace; font-size: 1.2em; background-color: #f8f9fa; padding: 2px 4px; border-radius: 3px;'>{$generatedPassword}</code>. Anótela si no va a cambiarla manualmente.");
        } else {
            // En la página de Edición
            $passwordField
                ->setLabel('Nueva Contraseña (Dejar vacío para no cambiar)')
                ->setFormTypeOption('attr', ['type' => 'password'])
                ->setHelp('Solo ingrese un valor si desea cambiar la contraseña existente.');
        }

        $fields[] = $passwordField;
        // ---------------------------

        $fields[] = ChoiceField::new('roles')
            ->setChoices([
                'Administrador' => 'ROLE_ADMIN',
                'Profesor' => 'ROLE_TEACHER',
                'Estudiante' => 'ROLE_STUDENT',
                'Apoderado' => 'ROLE_GUARDIAN',
            ])
            ->allowMultipleChoices(true)
            ->renderExpanded(false)
            ->renderAsBadges();

        // Lógica condicional para 'Pupilos' (Guardians)
        if ($pageName !== Crud::PAGE_NEW) {
            if ($currentUser instanceof User && in_array('ROLE_GUARDIAN', $currentUser->getRoles())) {
                $fields[] = AssociationField::new('guardianStudents', 'Pupilos')
                    ->setFormTypeOptions([
                        'by_reference' => false,
                        'query_builder' => fn ($repo) => $repo->createQueryBuilder('u')
                            ->where('u.roles LIKE :role')
                            ->setParameter('role', '%ROLE_STUDENT%')
                            ->orderBy('u.fullName', 'ASC'),
                    ])
                    ->setHelp('Solo muestra usuarios con rol Estudiante.')
                    ->onlyOnForms();
            }
        }

        return $fields;
    }

    // 2. HASHEAR LA CONTRASEÑA ANTES DE GUARDAR (CREACIÓN)
    public function persistEntity(EntityManagerInterface $entityManager, $entity): void
    {
        $this->hashPasswordIfRequired($entity);
        parent::persistEntity($entityManager, $entity);
    }

    // 3. HASHEAR LA CONTRASEÑA ANTES DE ACTUALIZAR (EDICIÓN)
    public function updateEntity(EntityManagerInterface $entityManager, $entity): void
    {
        $this->hashPasswordIfRequired($entity);
        parent::updateEntity($entityManager, $entity);
    }

    /**
     * Revisa si se proporcionó una nueva contraseña en el campo unmapped y la hashea,
     * obteniendo el valor directamente de la solicitud POST.
     * Ahora también actualiza la contraseña temporal si se cambia el RUT en la creación.
     */
    private function hashPasswordIfRequired(User $user): void
    {
        // 💡 FIX: Acceder al formulario subyacente a través del Request, ya que getContext()->getForm() falla.
        $request = $this->requestStack->getCurrentRequest();
        $plainPassword = null;
        $isNewPage = $this->getContext()->getCrud()->getCurrentPage() === Crud::PAGE_NEW;

        if ($request && $request->isMethod('POST')) {
            $submittedData = $request->request->all();

            // Buscamos el campo 'plainPassword' dentro del array de datos posteados.
            // Los formularios de EasyAdmin suelen tener un nombre de formulario (ej. 'user_form' o similar).
            foreach ($submittedData as $data) {
                if (is_array($data) && isset($data['plainPassword'])) {
                    $plainPassword = $data['plainPassword'];
                    break;
                }
            }
        }

        // --- Nueva Lógica: Actualizar contraseña temporal si es creación y no se ingresó manualmente ---
        if ($isNewPage && !$plainPassword) {
            // Si no se ingresó manualmente una contraseña nueva, usamos la basada en RUT
            $rut = $user->getRut();
            if ($rut) {
                $digits = preg_replace('/[^0-9]/', '', $rut);
                $plainPassword = substr($digits, 0, 6);
            }
            // Si no hay RUT aún (caso raro en PAGE_NEW si se envió el form), plainPassword seguirá siendo null o vacío.
        }
        // --- Fin Nueva Lógica ---

        // Solo hashear si se proporcionó un valor (manual o por defecto basado en RUT)
        if ($plainPassword) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
        }

        // Limpiar la propiedad temporal después de usarla (opcional, pero buena práctica)
        if (isset($user->plainPassword)) {
            unset($user->plainPassword);
        }
    }
}
