<?php

namespace App\DataFixtures;

use App\Entity\CourseCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseCategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $categories = [
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

        foreach ($categories as $data) {
            $category = new CourseCategory();
            $category->setArea($data['area']);
            $category->setName($data['name']);
            $manager->persist($category);
        }

        $manager->flush();
    }
}
