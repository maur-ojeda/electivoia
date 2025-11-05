<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;

class UserCrudController extends AbstractCrudController
{
    private const CHILEAN_GRADES = [
        '3° Medio' => '3M',
        '4° Medio' => '4M',
    ];

    // Longitud de la contraseña temporal (usa un número par)
    private const TEMPORARY_PASSWORD_LENGTH = 16;

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private RequestStack $requestStack // 💡 FIX: Inyectamos RequestStack para acceder al formulario
    ) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    // 1. GENERAR CONTRASEÑA TEMPORAL Y ASIGNARLA A LA PROPIEDAD NO MAPEADA 'plainPassword'.
    public function createEntity(string $entityFqcn): User
    {
        /** @var User $user */
        $user = new $entityFqcn();

        // Generar una contraseña aleatoria y segura.
        $plainPassword = substr(bin2hex(random_bytes(self::TEMPORARY_PASSWORD_LENGTH / 2)), 0, self::TEMPORARY_PASSWORD_LENGTH);

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
            TextField::new('rut', 'RUT')
                ->setHelp('Formato chileno: 12345678-9')
                ->setRequired(true),




            EmailField::new('email'),





            ChoiceField::new('grade', 'Curso/Grado')
                ->setChoices(self::CHILEAN_GRADES)
                ->allowMultipleChoices(false)
                ->renderAsNativeWidget(), // Muestra como <select> estándar
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
                    'readonly' => 'readonly'
                ])
                ->setHelp("Contraseña generada: <code style='font-size: 1.1em;'>{$generatedPassword}</code>. Anótela si no va a cambiarla manualmente.");
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
                    ])
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

        // Si el valor está vacío, pero estamos en la página de creación,
        // tomamos la contraseña temporal que se generó en createEntity.
        if (!$plainPassword && $isNewPage && isset($user->plainPassword)) {
            $plainPassword = $user->plainPassword;
        }

        // Solo hashear si se proporcionó un valor (manual o por defecto)
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
