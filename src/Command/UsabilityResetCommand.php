<?php

namespace App\Command;

use App\Entity\AiSignal;
use App\Entity\Attendance;
use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\School;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:usability:reset',
    description: 'Delete all usability-seed data and re-seed (requires USABILITY_SEED_ENABLED=1)',
)]
class UsabilityResetCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (($_SERVER['USABILITY_SEED_ENABLED'] ?? getenv('USABILITY_SEED_ENABLED') ?? '') !== '1') {
            $io->error('Set USABILITY_SEED_ENABLED=1 to run this command.');
            return Command::FAILURE;
        }

        $this->em->wrapInTransaction(function () use ($io): void {
            $conn = $this->em->getConnection();

            // Find seeded schools
            $schools = $this->em->getRepository(School::class)->findBy([
                'slug' => ['liceo-ficticio-electivoia', 'colegio-aislamiento'],
            ]);
            $schoolIds = array_map(static fn(School $s) => $s->getId(), $schools);

            // Find ALL seeded users: by school OR by @electivoia.test email (covers SA1)
            $qb = $this->em->createQueryBuilder();
            $where = 'u.email LIKE :suffix';
            $params = ['suffix' => '%@electivoia.test'];
            if ($schoolIds) {
                $where .= ' OR u.school IN (:schools)';
                $params['schools'] = $schoolIds;
            }
            $users = $qb->select('u')->from(User::class, 'u')
                ->where($where)
                ->getQuery()
                ->setParameters($params)
                ->getResult();

            if (!$users && !$schools) {
                $io->note('Nothing to delete — seed was not run.');
                return;
            }

            $userIds = array_map(static fn(User $u) => $u->getId(), $users);

            // 1. AiSignal
            if ($userIds) {
                $this->em->createQuery('DELETE FROM ' . AiSignal::class . ' a WHERE a.student IN (:ids)')
                    ->setParameter('ids', $userIds)->execute();
            }
            if ($schoolIds) {
                $this->em->createQuery('DELETE FROM ' . AiSignal::class . ' a WHERE a.school IN (:ids)')
                    ->setParameter('ids', $schoolIds)->execute();
            }

            // 2. Attendance (by user AND by course-school to catch any orphaned records)
            if ($userIds) {
                $this->em->createQuery('DELETE FROM ' . Attendance::class . ' a WHERE a.student IN (:ids)')
                    ->setParameter('ids', $userIds)->execute();
            }

            // 3. Enrollment
            if ($userIds) {
                $this->em->createQuery('DELETE FROM ' . Enrollment::class . ' e WHERE e.student IN (:ids)')
                    ->setParameter('ids', $userIds)->execute();
            }

            // 4. Guardian ManyToMany join table (user_user: user_source, user_target)
            if ($userIds) {
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $ids = array_values($userIds);
                $conn->executeStatement(
                    "DELETE FROM user_user WHERE user_source IN ({$placeholders}) OR user_target IN ({$placeholders})",
                    array_merge($ids, $ids)
                );
            }

            // 5. Courses (deletes before Users to satisfy FK on teacher)
            if ($schoolIds) {
                $this->em->createQuery('DELETE FROM ' . Course::class . ' c WHERE c.school IN (:ids)')
                    ->setParameter('ids', $schoolIds)->execute();
            }

            // 6. Users — InterestProfile cascades automatically
            if ($userIds) {
                $this->em->createQuery('DELETE FROM ' . User::class . ' u WHERE u.id IN (:ids)')
                    ->setParameter('ids', $userIds)->execute();
            }

            // 7. Schools
            foreach ($schools as $school) {
                $this->em->remove($school);
            }
            $this->em->flush();

            $io->success(sprintf(
                'Deleted %d users, %d schools. CourseCategory unchanged.',
                count($userIds),
                count($schools)
            ));
        });

        return Command::SUCCESS;
    }
}
