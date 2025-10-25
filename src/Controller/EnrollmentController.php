<?php


namespace App\Controller;



use App\Entity\Enrollment;

use App\Entity\Course;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\Security\Http\Attribute\IsGranted;



#[Route('/enrollment', name: 'enrollment_')]

class EnrollmentController extends AbstractController

{

    #[Route('/{id}/enroll', name: 'enroll', methods: ['POST'])]

    #[IsGranted('ROLE_STUDENT')]

    public function enroll(

        Course $course,

        EntityManagerInterface $em,

        Request $request

    ): Response {

        $student = $this->getUser();



        // Validar: curso activo

        if (!$course->isActive()) {

            $this->addFlash('error', 'El curso no está disponible.');

            return $this->redirectToRoute('student_courses');
        }



        // Validar: ya inscrito

        $existing = $em->getRepository(Enrollment::class)->findOneBy([

            'student' => $student,

            'course' => $course

        ]);

        if ($existing) {

            $this->addFlash('warning', 'Ya estás inscrito en este curso.');

            return $this->redirectToRoute('student_courses');
        }



        $currentCount = $em->getRepository(Enrollment::class)->count(['course' => $course]);

        $maxCapacity = $course->getMaxCapacity();



        if ($currentCount < $maxCapacity) {

            // Hay cupo → inscribir directamente

            $enrollment = new Enrollment();

            $enrollment->setStudent($student);

            $enrollment->setCourse($course);

            $enrollment->setEnrolledAt(new \DateTime());

            $em->persist($enrollment);

            $em->flush();

            $this->addFlash('success', '¡Inscripción exitosa!');
        } else {

            // Sin cupo → aplicar regla de prioridad

            $studentGrade = $student->getAverageGrade();

            if ($studentGrade === null) {

                $this->addFlash('error', 'No puedes inscribirte sin promedio registrado.');

                return $this->redirectToRoute('student_courses');
            }



            // Buscar estudiante con menor promedio

            $lowestEnrollment = $em->createQuery('

SELECT e FROM App\Entity\Enrollment e

JOIN e.student s

WHERE e.course = :course

ORDER BY COALESCE(s.averageGrade, 0) ASC

')

                ->setParameter('course', $course)

                ->setMaxResults(1)

                ->getOneOrNullResult();



            if ($lowestEnrollment) {

                $lowestStudent = $lowestEnrollment->getStudent();

                $lowestGrade = $lowestStudent->getAverageGrade() ?? 0;



                if ($studentGrade > $lowestGrade) {

                    // Desplazar al estudiante con menor promedio

                    $em->remove($lowestEnrollment);

                    $newEnrollment = new Enrollment();

                    $newEnrollment->setStudent($student);

                    $newEnrollment->setCourse($course);

                    $newEnrollment->setEnrolledAt(new \DateTime());

                    $em->persist($newEnrollment);

                    $em->flush();

                    $this->addFlash('success', "¡Inscripción exitosa! Reemplazaste a {$lowestStudent->getEmail()}.");
                } else {

                    $this->addFlash('error', 'No hay cupo y tu promedio no es suficiente.');
                }
            } else {

                $this->addFlash('error', 'Error al verificar prioridad.');
            }
        }



        return $this->redirectToRoute('student_courses');
    }
}
