<?php

namespace App\Service;

use App\Entity\AiSignal;
use App\Entity\Course;
use App\Entity\User;
use App\Repository\AiSignalRepository;
use Doctrine\ORM\EntityManagerInterface;

class SignalCollectionService
{
    public function __construct(
        private AiSignalRepository $signalRepository,
        private EntityManagerInterface $em,
    ) {}

    /**
     * Creates and persists a signal. Does NOT flush — the caller decides when to flush.
     *
     * @param array<string, mixed> $metadata
     */
    public function record(
        User $student,
        string $action,
        ?Course $course = null,
        array $metadata = [],
    ): AiSignal {
        $signal = new AiSignal();
        $signal->setStudent($student);
        $signal->setAction($action);
        $signal->setCourse($course);
        $signal->setMetadata(!empty($metadata) ? $metadata : null);
        $signal->setCreatedAt(new \DateTimeImmutable());
        $signal->setSchool($student->getSchool());

        $this->em->persist($signal);

        return $signal;
    }

    /**
     * Returns an array of course IDs the student has viewed.
     *
     * @return int[]
     */
    public function getCourseIdsViewedByStudent(User $student): array
    {
        return $this->signalRepository->findViewedCourseIds($student);
    }
}
