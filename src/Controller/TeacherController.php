<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/teacher', name: 'teacher_')]
#[IsGranted('ROLE_TEACHER')]
class TeacherController extends AbstractController
{
    #[Route('/courses', name: 'courses')]
    public function courses(EntityManagerInterface $em): Response
    {
        $teacher = $this->getUser();
        $courses = $em->getRepository(\App\Entity\Course::class)->findBy(['teacher' => $teacher]);

        $enrollmentsByCourse = [];
        foreach ($courses as $course) {
            $enrollments = $em->getRepository(\App\Entity\Enrollment::class)->findBy(['course' => $course]);
            $enrollmentsByCourse[$course->getId()] = $enrollments;
        }

        return $this->render('teacher/courses.html.twig', [
            'courses' => $courses,
            'enrollmentsByCourse' => $enrollmentsByCourse,
        ]);
    }
}
