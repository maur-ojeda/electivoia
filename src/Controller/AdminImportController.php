<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users', name: 'admin_users_')]
#[IsGranted('ROLE_ADMIN')]
class AdminImportController extends AbstractController
{
    #[Route('/import', name: 'import', methods: ['GET', 'POST'])]
    public function import(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $result = null;

        if ($request->isMethod('POST')) {
            $file = $request->files->get('csv_file');

            if (!$file || $file->getClientOriginalExtension() !== 'csv') {
                $this->addFlash('error', 'Debes subir un archivo con extensión .csv');
                return $this->redirectToRoute('admin_users_import');
            }

            $handle = fopen($file->getPathname(), 'r');
            if ($handle === false) {
                $this->addFlash('error', 'No se pudo leer el archivo.');
                return $this->redirectToRoute('admin_users_import');
            }

            $created = 0;
            $duplicates = 0;
            $errors = [];
            $lineNumber = 0;

            // Skip header row
            fgetcsv($handle);

            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $lineNumber++;

                // Support semicolon separator fallback
                if (count($row) === 1) {
                    $row = str_getcsv($row[0], ';');
                }

                if (count($row) < 3) {
                    $errors[] = "Línea {$lineNumber}: formato incorrecto (se esperan al menos 3 columnas).";
                    continue;
                }

                [$rut, $fullName, $grade] = array_map('trim', $row);
                $averageGrade = isset($row[3]) && $row[3] !== '' ? (float) str_replace(',', '.', trim($row[3])) : null;
                $email = isset($row[4]) && trim($row[4]) !== '' ? trim($row[4]) : null;

                // Validate RUT format
                if (!preg_match('/^\d{7,8}-[\dKk]$/', $rut)) {
                    $errors[] = "Línea {$lineNumber}: RUT inválido '{$rut}'.";
                    continue;
                }

                // Validate grade
                if (!in_array($grade, ['3M', '4M'])) {
                    $errors[] = "Línea {$lineNumber}: grado inválido '{$grade}' (debe ser 3M o 4M).";
                    continue;
                }

                // Check duplicate
                $existing = $em->getRepository(User::class)->findOneBy(['rut' => $rut]);
                if ($existing) {
                    $duplicates++;
                    continue;
                }

                // Check email duplicate if provided
                if ($email) {
                    $existingEmail = $em->getRepository(User::class)->findOneBy(['email' => $email]);
                    if ($existingEmail) {
                        $email = null; // silently drop duplicate email
                    }
                }

                // Password = first digits of RUT (before dash)
                $passwordRaw = explode('-', $rut)[0];

                $user = new User();
                $user->setRut($rut);
                $user->setFullName($fullName ?: $rut);
                $user->setGrade($grade);
                $user->setRoles(['ROLE_STUDENT']);
                $user->setActive(true);

                if ($averageGrade !== null) {
                    $user->setAverageGrade($averageGrade);
                }
                if ($email) {
                    $user->setEmail($email);
                }

                $hashed = $hasher->hashPassword($user, $passwordRaw);
                $user->setPassword($hashed);

                $em->persist($user);
                $created++;
            }

            fclose($handle);
            $em->flush();

            $result = [
                'created'    => $created,
                'duplicates' => $duplicates,
                'errors'     => $errors,
            ];
        }

        return $this->render('admin/import.html.twig', [
            'result' => $result,
        ]);
    }
}
