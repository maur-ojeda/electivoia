<?php

namespace App\Controller;

use App\Entity\CourseCategory;
use App\Entity\School;
use App\Entity\User;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/super-admin', name: 'super_admin_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class SuperAdminController extends AbstractController
{
    // Default Chilean curriculum categories
    private const DEFAULT_CATEGORIES = [
        ['name' => 'Filosofía',                                    'area' => 'Hum'],
        ['name' => 'Matemática',                                   'area' => 'Cien'],
        ['name' => 'Educación física y salud',                     'area' => 'EF'],
        ['name' => 'Historia, geografía y ciencias sociales',      'area' => 'Hum'],
        ['name' => 'Ciencias',                                     'area' => 'Cien'],
        ['name' => 'Lengua y literatura',                          'area' => 'Hum'],
        ['name' => 'Artes',                                        'area' => 'Arte'],
    ];

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private NotificationService $notificationService,
        private SluggerInterface $slugger
    ) {}

    #[Route('', name: 'dashboard')]
    public function dashboard(EntityManagerInterface $em): Response
    {
        $schools   = $em->getRepository(School::class)->findBy([], ['createdAt' => 'DESC']);
        $totalUsers = $em->getRepository(User::class)->count([]);
        $totalSchools = count($schools);

        $schoolStats = [];
        foreach ($schools as $school) {
            $users   = $em->getRepository(User::class)->count(['school' => $school]);
            $schoolStats[] = ['school' => $school, 'users' => $users];
        }

        return $this->render('super_admin/dashboard.html.twig', [
            'schoolStats'  => $schoolStats,
            'totalSchools' => $totalSchools,
            'totalUsers'   => $totalUsers,
        ]);
    }

    #[Route('/onboard', name: 'onboard', methods: ['GET', 'POST'])]
    public function onboard(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            // --- School ---
            $school = new School();
            $school->setName($request->request->get('schoolName'));
            $school->setRbd($request->request->get('rbd') ?: null);
            $school->setPlan($request->request->get('plan', 'free'));

            $rawSlug = $request->request->get('slug') ?: $school->getName();
            $school->setSlug(strtolower($this->slugger->slug($rawSlug)->toString()));

            $em->persist($school);

            // --- Admin user ---
            $adminEmail    = $request->request->get('adminEmail');
            $adminFullName = $request->request->get('adminFullName');
            $adminRut      = $request->request->get('adminRut');
            $plainPassword = bin2hex(random_bytes(8)); // 16-char secure password

            $admin = new User();
            $admin->setFullName($adminFullName);
            $admin->setEmail($adminEmail);
            $admin->setRut($adminRut);
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setSchool($school);
            $admin->setActive(true);
            $admin->setPassword(
                $this->passwordHasher->hashPassword($admin, $plainPassword)
            );

            $em->persist($admin);

            // --- HDU-S7-03: Seed default categories if none exist ---
            $existingCount = $em->getRepository(CourseCategory::class)->count([]);
            if ($existingCount === 0) {
                foreach (self::DEFAULT_CATEGORIES as $cat) {
                    $category = new CourseCategory();
                    $category->setName($cat['name']);
                    $category->setArea($cat['area']);
                    $em->persist($category);
                }
            }

            $em->flush();

            // --- HDU-S7-02: Welcome email ---
            $this->notificationService->sendSchoolWelcome($admin, $school, $plainPassword);

            $this->addFlash('success', "Colegio \"{$school->getName()}\" creado. Email de bienvenida enviado a {$adminEmail}.");
            return $this->redirectToRoute('super_admin_dashboard');
        }

        return $this->render('super_admin/onboard.html.twig');
    }
}
