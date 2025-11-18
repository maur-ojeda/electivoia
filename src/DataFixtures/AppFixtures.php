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
            ['area' => 'Área A', 'name' => 'Filosofía'],
            ['area' => 'Área A', 'name' => 'Historia, geografía y ciencias sociales'],
            ['area' => 'Área A', 'name' => 'Lengua y literatura'],
            ['area' => 'Área B', 'name' => 'Matemática'],
            ['area' => 'Área B', 'name' => 'Ciencias'],
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
        $admin->setRut('13473632-1');
        $admin->setFullName('ADMINISTRADOR PRINCIPAL');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setGender('M');
        // Contraseña inicial: primeros 6 dígitos del RUT -> '134736'
        $plainPasswordAdmin = substr('13473632', 0, 6);
        $hashedPasswordAdmin = $this->passwordHasher->hashPassword($admin, $plainPasswordAdmin);
        $admin->setPassword($hashedPasswordAdmin);
        $manager->persist($admin);

        // Profesor
        $teacher = new User();
        $teacher->setRut('15662884-0');
        $teacher->setFullName('María Carolina Garcia');
        $teacher->setRoles(['ROLE_TEACHER']);
        $teacher->setGender('F');
        // Contraseña inicial: primeros 6 dígitos del RUT -> '156628'
        $plainPasswordTeacher = substr('15662884', 0, 6);
        $hashedPasswordTeacher = $this->passwordHasher->hashPassword($teacher, $plainPasswordTeacher);
        $teacher->setPassword($hashedPasswordTeacher);
        $manager->persist($teacher);

        // Estudiantes
        $studentData = [
            ['rut' => '26926162-5', 'fullName' => 'ARBOLEDA ESCOBAR JOHAN SIFREDO', 'grade' => '4M', 'avgGrade' => 6.8, 'gender' => 'M'],
            ['rut' => '27806920-6', 'fullName' => 'BARAZARTE POLANCO KEINER XAVIER', 'grade' => '3M', 'avgGrade' => 5.2, 'gender' => 'M'],
            ['rut' => '100710029-5', 'fullName' => 'BARRIOS RIVERO MARLYN ARACELIS', 'grade' => '3M', 'avgGrade' => 7.0, 'gender' => 'F'],
            ['rut' => '22833420-0', 'fullName' => 'BRAVO MORALES RENATO IGNACIO', 'grade' => '4M', 'avgGrade' => 4.9, 'gender' => 'M'],
            ['rut' => '23077101-4', 'fullName' => 'CORDOVA LLAURE CATALINA NAOME', 'grade' => '4M', 'avgGrade' => 6.5, 'gender' => 'F'],
            ['rut' => '28054236-9', 'fullName' => 'GARCIA VELASQUEZ JULIETTE STEFANIA', 'grade' => '4M', 'avgGrade' => 6.0, 'gender' => 'F'],
            ['rut' => '28190953-3', 'fullName' => 'GARCIA VERGARA JOSMAN DANIEL', 'grade' => '3M', 'avgGrade' => 5.5, 'gender' => 'M'],
            ['rut' => '25781301-0', 'fullName' => 'GIHUA OYOS ABEL CRISTOFER', 'grade' => '4M', 'avgGrade' => 6.2, 'gender' => 'M'],
            ['rut' => '100712488-7', 'fullName' => 'GOMEZ ESCOBAR ERICK JHONEIKER', 'grade' => '3M', 'avgGrade' => 5.8, 'gender' => 'M'],
            ['rut' => '27194888-3', 'fullName' => 'GRATEROL YORES SAMUEL DAVID', 'grade' => '4M', 'avgGrade' => 6.7, 'gender' => 'M'],
            ['rut' => '26726409-0', 'fullName' => 'HERNANDEZ RANGEL ANGELICA VALENTINA', 'grade' => '4M', 'avgGrade' => 6.9, 'gender' => 'F'],
            ['rut' => '100540276-6', 'fullName' => 'HERNANDEZ SOSA SANTIAGO MIGUEL', 'grade' => '3M', 'avgGrade' => 5.1, 'gender' => 'M'],
            ['rut' => '100678721-1', 'fullName' => 'LAREZ ASTUDILLO KAMILA VALENTINA', 'grade' => '3M', 'avgGrade' => 7.2, 'gender' => 'F'],
            ['rut' => '100599728-K', 'fullName' => 'MEDINA TUDARES JOSIBEL DEL VALLE', 'grade' => '4M', 'avgGrade' => 6.4, 'gender' => 'F'],
            ['rut' => '28577509-4', 'fullName' => 'MENDOZA RIVAS ROSBEILY ANYELI', 'grade' => '3M', 'avgGrade' => 5.9, 'gender' => 'F'],
            ['rut' => '100579706-K', 'fullName' => 'MOLERO SARMIENTO GABRIELA ISABEL', 'grade' => '4M', 'avgGrade' => 7.1, 'gender' => 'F'],
            ['rut' => '100514139-3', 'fullName' => 'MORALES LUGO ALEXANYELI DENILEY', 'grade' => '3M', 'avgGrade' => 5.6, 'gender' => 'F'],
            ['rut' => '28610388-K', 'fullName' => 'ORTIZ PALACIOS DELINYER EDGARDO', 'grade' => '4M', 'avgGrade' => 6.1, 'gender' => 'M'],
            ['rut' => '28074145-0', 'fullName' => 'PERDOMO ARIAS MELANY SOFIA', 'grade' => '3M', 'avgGrade' => 6.3, 'gender' => 'F'],
            ['rut' => '100722894-1', 'fullName' => 'PEREZ DE LA VILLA JOSNEILLY VALENTINA', 'grade' => '4M', 'avgGrade' => 6.8, 'gender' => 'F'],
            ['rut' => '100782703-9', 'fullName' => 'PRADO ORTIZ SEBASTIAN', 'grade' => '3M', 'avgGrade' => 5.3, 'gender' => 'M'],
            ['rut' => '26006714-1', 'fullName' => 'QUIJADA MORON SARAH VALENTINA', 'grade' => '4M', 'avgGrade' => 7.0, 'gender' => 'F'],
            ['rut' => '26337386-3', 'fullName' => 'ROMERO POZO VICTOR DANIEL', 'grade' => '3M', 'avgGrade' => 5.7, 'gender' => 'M'],
            ['rut' => '100567883-4', 'fullName' => 'SANDOVAL MORENO GENESIS VIRGINIA', 'grade' => '4M', 'avgGrade' => 6.6, 'gender' => 'F'],
            ['rut' => '28249815-4', 'fullName' => 'SUAREZ CONTRERAS FREIVIANNY VALENTINA', 'grade' => '3M', 'avgGrade' => 5.8, 'gender' => 'F'],
            ['rut' => '26238107-2', 'fullName' => 'TORREALBA RIVAS LUIS ENRIQUE', 'grade' => '4M', 'avgGrade' => 6.2, 'gender' => 'M'],
            ['rut' => '23070959-9', 'fullName' => 'TORRES PASTENE TANIA SOFÍA', 'grade' => '3M', 'avgGrade' => 6.9, 'gender' => 'F'],
            ['rut' => '28060441-0', 'fullName' => 'VERDESIA MEJIA FABIANA CAROLINA', 'grade' => '4M', 'avgGrade' => 6.4, 'gender' => 'F'],
            ['rut' => '100795916-4', 'fullName' => 'DIAZ VENTE KENNY ALEXANDER', 'grade' => '3M', 'avgGrade' => 5.5, 'gender' => 'M'],
            ['rut' => '100769394-6', 'fullName' => 'ROA AGUILAR DAVIANA VICTORIA', 'grade' => '4M', 'avgGrade' => 6.7, 'gender' => 'F'],
            ['rut' => '28340204-5', 'fullName' => 'LUGO GAMEZ MARIANNYS ROSA', 'grade' => '3M', 'avgGrade' => 5.9, 'gender' => 'F'],
            ['rut' => '27024297-9', 'fullName' => 'ALVAREZ CASTELLANOS DIEGO IGNACIO', 'grade' => '4M', 'avgGrade' => 6.3, 'gender' => 'M'],
            ['rut' => '100802720-6', 'fullName' => 'LEON RONQUILLO SCARLETT PAULETTE', 'grade' => '3M', 'avgGrade' => 7.1, 'gender' => 'F'],
            ['rut' => '28902279-1', 'fullName' => 'WILCHEZ LOPEZ GYSLAINE ANGELINE', 'grade' => '4M', 'avgGrade' => 6.5, 'gender' => 'F'],
            ['rut' => '28971158-9', 'fullName' => 'SALAZAR MILANO DANIELYS CELESTE', 'grade' => '3M', 'avgGrade' => 5.4, 'gender' => 'F'],
            ['rut' => '22686558-6', 'fullName' => 'CARVACHO MORGADO GONZALO LUCIANO', 'grade' => '4M', 'avgGrade' => 6.0, 'gender' => 'M'],
            ['rut' => '100839305-9', 'fullName' => 'TOLEDO MARTIN ALEXANDER EXEQUIEL', 'grade' => '3M', 'avgGrade' => 5.6, 'gender' => 'M'],
        ];

        foreach ($studentData as $data) {
            $student = new User();
            $student->setRut($data['rut']);
            $student->setFullName($data['fullName']);
            $student->setRoles(['ROLE_STUDENT']);
            $student->setAverageGrade($data['avgGrade']);
            $student->setGrade($data['grade']); // Asegúrate de que este valor coincida con tu constante CHILEAN_GRADES
            $student->setGender($data['gender']);
            // Contraseña inicial: primeros 6 dígitos del RUT
            $plainPasswordStudent = substr(str_replace('-', '', $data['rut']), 0, 6);
            $hashedPasswordStudent = $this->passwordHasher->hashPassword($student, $plainPasswordStudent);
            $student->setPassword($hashedPasswordStudent);
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
                // $course->setSchedule('Por definir'); // Comentado: ya no se usa schedule
                $course->setTeacher($teacher); // ← Usamos el profesor creado arriba
                $course->setCategory($category);
                $manager->persist($course);
            }
        }

        $manager->flush();
    }
}
