<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher) {}

    public function load(ObjectManager $manager): void
    {

        $admin = new User();
        $admin->setEmail('admin@electivoia.local');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, '123456'));
        $manager->persist($admin);


        // Profesores
        $teacher1 = new User();
        $teacher1->setEmail('profesor1@electivoia.local');
        $teacher1->setRoles(['ROLE_TEACHER']);
        $teacher1->setPassword($this->passwordHasher->hashPassword($teacher1, '123456'));
        $manager->persist($teacher1);

        // Estudiantes
        $students = [];
        $grades = [6.8, 5.2, 7.0, 4.9, 6.5];
        foreach ($grades as $i => $grade) {
            $student = new User();
            $student->setEmail("estudiante{$i}@electivoia.local");
            $student->setRoles(['ROLE_STUDENT']);
            $student->setAverageGrade($grade);
            $student->setPassword($this->passwordHasher->hashPassword($student, '123456'));
            $manager->persist($student);
            $students[] = $student;
            $student->setGrade('8B');
        }

        // Cursos
        $course1 = new Course();
        $course1->setName('Robótica');
        $course1->setDescription('Construcción y programación de robots');
        $course1->setMaxCapacity(3);
        $course1->setSchedule('Lun-Vie 15:00');
        $course1->setTeacher($teacher1);
        $manager->persist($course1);

        $manager->flush();
    }
}
