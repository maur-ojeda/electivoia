<?php

namespace App\Controller;

use App\Entity\School;
use App\Entity\User;
use App\Repository\SchoolRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register/{slug}', name: 'app_register')]
    public function register(
        string $slug,
        Request $request,
        SchoolRepository $schoolRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        // Redirect authenticated users away
        if ($this->getUser()) {
            return $this->redirectToRoute('student_courses');
        }

        $school = $schoolRepository->findBySlug($slug);

        if (!$school || !$school->isActive()) {
            throw $this->createNotFoundException('El colegio no existe o no está activo.');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $fullName     = trim($request->request->get('fullName', ''));
            $rut          = trim($request->request->get('rut', ''));
            $email        = trim($request->request->get('email', '')) ?: null;
            $grade        = $request->request->get('grade', '');
            $plainPassword = trim($request->request->get('password', ''));
            $confirmPass  = trim($request->request->get('confirmPassword', ''));

            // Validaciones básicas
            if (!$fullName) {
                $errors[] = 'El nombre completo es obligatorio.';
            }
            if (!preg_match('/^\d{7,8}-[\dKk]$/', $rut)) {
                $errors[] = 'El RUT debe tener el formato 12345678-9.';
            }
            if (!in_array($grade, ['3M', '4M'], true)) {
                $errors[] = 'El grado debe ser 3M o 4M.';
            }
            if (strlen($plainPassword) < 6) {
                $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
            }
            if ($plainPassword !== $confirmPass) {
                $errors[] = 'Las contraseñas no coinciden.';
            }
            if (empty($errors) && $em->getRepository(User::class)->findOneBy(['rut' => $rut])) {
                $errors[] = 'Ya existe una cuenta con este RUT.';
            }
            if ($email && empty($errors) && $em->getRepository(User::class)->findOneBy(['email' => $email])) {
                $errors[] = 'Ya existe una cuenta con este email.';
            }

            if (empty($errors)) {
                $user = new User();
                $user->setFullName($fullName);
                $user->setRut($rut);
                $user->setEmail($email);
                $user->setGrade($grade);
                $user->setRoles(['ROLE_STUDENT']);
                $user->setSchool($school);
                $user->setActive(true);
                $user->setPassword($hasher->hashPassword($user, $plainPassword));

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', '¡Cuenta creada! Ya puedes iniciar sesión con tu RUT.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', [
            'school' => $school,
            'errors' => $errors,
        ]);
    }
}
