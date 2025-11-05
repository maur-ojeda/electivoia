<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\CourseCategory;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher) {}

    public function load(ObjectManager $manager): void
    {
        // =============
        // 1. CATEGORÍAS
        // =============
        $categoriesData = [
            // Área A
            ['area' => 'Área A', 'name' => 'Filosofía'],
            ['area' => 'Área A', 'name' => 'Historia, geografía y ciencias sociales'],
            ['area' => 'Área A', 'name' => 'Lengua y literatura'],
            // Área B
            ['area' => 'Área B', 'name' => 'Matemática'],
            ['area' => 'Área B', 'name' => 'Ciencias'],
            // Área C
            ['area' => 'Área C', 'name' => 'Artes'],
            ['area' => 'Área C', 'name' => 'Educación física y salud'],
        ];

        $categories = [];
        foreach ($categoriesData as $data) {
            $cat = new CourseCategory();
            $cat->setArea($data['area']);
            $cat->setName($data['name']);
            $manager->persist($cat);
            $categories[$data['name']] = $cat;
        }

        // =============
        // 2. USUARIOS
        // =============
        // Admin
        $admin = new User();
        $admin->setEmail('admin@electivoia.local');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, '123456'));
        $manager->persist($admin);

        // Profesor (¡guardamos la referencia aquí!)
        $teacher = new User();
        $teacher->setEmail('profesor1@electivoia.local');
        $teacher->setRoles(['ROLE_TEACHER']);
        $teacher->setPassword($this->passwordHasher->hashPassword($teacher, '123456'));
        $manager->persist($teacher);

        // Estudiantes
        $grades = [6.8, 5.2, 7.0, 4.9, 6.5];
        foreach ($grades as $i => $grade) {
            $student = new User();
            $student->setEmail("estudiante{$i}@electivoia.local");
            $student->setRoles(['ROLE_STUDENT']);
            $student->setAverageGrade($grade);
            $student->setGrade('8B');
            $student->setPassword($this->passwordHasher->hashPassword($student, '123456'));
            $manager->persist($student);
        }

        // =============
        // 3. CURSOS
        // =============
        $coursesData = [
            'Filosofía' => [
                'Estética',
                'Filosofía política',
                'Seminario de filosofía',
            ],
            'Historia, geografía y ciencias sociales' => [
                'Comprensión histórica del presente',
                'Geografía, territorio y desafíos socioambientales',
                'Economía y sociedad',
            ],
            'Lengua y literatura' => [
                'Taller de literatura',
                'Lectura y escritura especializadas',
                'Participación y argumentación en democracia',
            ],
            'Matemática' => [
                'Límites, derivadas e integrales',
                'Probabilidades y estadística descriptiva e inferencial',
                'Pensamiento computacional y programación',
                'Geometría 3D',
            ],
            'Ciencias' => [
                'Biología de los ecosistemas',
                'Biología celular y molecular',
                'Ciencias de la salud',
                'Física',
                'Química',
            ],
            'Artes' => [
                'Artes visuales, audiovisuales y multimediales',
                'Creación y composición musical',
                'Diseño y arquitectura',
                'Interpretación y creación en danza',
                'Interpretación y creación en teatro',
                'Interpretación musical',
            ],
            'Educación física y salud' => [
                'Promoción de estilos de vida activos y saludables',
                'Ciencias del ejercicio físico y deportivo',
                'Expresión corporal',
            ],
        ];

        foreach ($coursesData as $categoryName => $courseNames) {
            $category = $categories[$categoryName] ?? null;
            if (!$category) continue;

            foreach ($courseNames as $courseName) {
                $course = new Course();
                $course->setName($courseName);
                $course->setDescription("Curso electivo de {$categoryName}: {$courseName}.");
                $course->setMaxCapacity(20);
                $course->setSchedule('Por definir');
                $course->setTeacher($teacher); // ← Usamos el profesor creado arriba
                $course->setCategory($category);
                $manager->persist($course);
            }
        }

        $manager->flush();
    }
}
