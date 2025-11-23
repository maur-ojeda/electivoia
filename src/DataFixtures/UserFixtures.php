<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


class UserFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher) {}

    public function load(ObjectManager $manager): void
    {
        // 1. Administrador
        $admin = new User();
        $admin->setEmail('admin@electivoia.local');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, '123456'));
        $manager->persist($admin);

        // 2. Profesor
        $teacher1 = new User();
        $teacher1->setEmail('profesor1@electivoia.local');
        $teacher1->setRoles(['ROLE_TEACHER']);
        $teacher1->setPassword($this->passwordHasher->hashPassword($teacher1, '123456'));
        $manager->persist($teacher1);

        // Agregamos una referencia al profesor para poder usarlo en CourseFixtures
        $this->addReference('teacher_1', $teacher1);


        // 3. Estudiantes
        $grades = [6.8, 5.2, 7.0, 4.9, 6.5];
        foreach ($grades as $i => $grade) {
            $student = new User();
            $student->setEmail("estudiante{$i}@electivoia.local");
            $student->setRoles(['ROLE_STUDENT']);
            $student->setAverageGrade($grade);
            $student->setPassword($this->passwordHasher->hashPassword($student, '123456'));
            $student->setGrade('8B');
            $manager->persist($student);
        }

        $manager->flush();
    }
}
