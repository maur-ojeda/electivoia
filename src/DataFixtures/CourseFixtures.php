<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\CourseCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;



class CourseFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // 1. Obtener el profesor de referencia
        $teacher = $this->getReference('teacher_1');


        // 2. Obtener todas las categorías por nombre para facilitar la asignación
        $categories = [];
        foreach ($manager->getRepository(CourseCategory::class)->findAll() as $cat) {
            $categories[$cat->getName()] = $cat;
        }

        // 3. Definir los cursos por categoría
        $coursesData = [
            // Área A: Filosofía
            'Filosofía' => [
                'Estética',
                'Filosofía política',
                'Seminario de filosofía',
            ],
            // Área A: Historia, geografía y ciencias sociales
            'Historia, geografía y ciencias sociales' => [
                'Comprensión histórica del presente',
                'Geografía, territorio y desafíos socioambientales',
                'Economía y sociedad',
            ],
            // Área A: Lengua y literatura
            'Lengua y literatura' => [
                'Taller de literatura',
                'Lectura y escritura especializadas',
                'Participación y argumentación en democracia',
            ],
            // Área B: Matemática
            'Matemática' => [
                'Límites, derivadas e integrales',
                'Probabilidades y estadística descriptiva e inferencial',
                'Pensamiento computacional y programación',
                'Geometría 3D',
            ],
            // Área B: Ciencias
            'Ciencias' => [
                'Biología de los ecosistemas',
                'Biología celular y molecular',
                'Ciencias de la salud',
                'Física',
                'Química',
            ],
            // Área C: Artes
            'Artes' => [
                'Artes visuales, audiovisuales y multimediales',
                'Creación y composición musical',
                'Diseño y arquitectura',
                'Interpretación y creación en danza',
                'Interpretación y creación en teatro',
                'Interpretación musical',
            ],
            // Área C: Educación física y salud
            'Educación física y salud' => [
                'Promoción de estilos de vida activos y saludables',
                'Ciencias del ejercicio físico y deportivo',
                'Expresión corporal',
            ],
        ];

        // 4. Crear los cursos
        foreach ($coursesData as $categoryName => $courseNames) {
            if (!isset($categories[$categoryName])) {
                continue; // Salta si la categoría no existe
            }
            $category = $categories[$categoryName];

            foreach ($courseNames as $courseName) {
                $course = new Course();
                $course->setName($courseName);
                $course->setDescription("Curso electivo de {$categoryName}: {$courseName}.");
                $course->setMaxCapacity(20);
                $course->setSchedule('Por definir');
                $course->setTeacher($teacher);
                $course->setCategory($category);
                $manager->persist($course);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            CourseCategoryFixtures::class,
        ];
    }
}
