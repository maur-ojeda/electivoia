<?php

namespace App\Command;

use App\Entity\Course;
use App\Entity\Enrollment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:alert-low-enrollment',
    description: 'Lista cursos con baja inscripción (por debajo del umbral de capacidad).',
)]
class AlertLowEnrollmentCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'threshold',
            't',
            InputOption::VALUE_OPTIONAL,
            'Porcentaje mínimo de ocupación (0-100). Cursos por debajo de este valor se reportan.',
            30
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $threshold = (int) $input->getOption('threshold') / 100;

        $courses = $this->em->getRepository(Course::class)->findBy(['isActive' => true]);

        $alerts = [];
        foreach ($courses as $course) {
            $enrolled = $this->em->getRepository(Enrollment::class)->count(['course' => $course]);
            $capacity = $course->getMaxCapacity();
            $pct = $capacity > 0 ? $enrolled / $capacity : 0;

            if ($pct < $threshold) {
                $alerts[] = [
                    $course->getId(),
                    $course->getName(),
                    $course->getTeacher()?->getFullName() ?? 'N/A',
                    $course->getSchedule() ?? '—',
                    $enrolled . '/' . $capacity,
                    round($pct * 100) . '%',
                ];
            }
        }

        if (empty($alerts)) {
            $io->success(sprintf('Ningún curso por debajo del %d%% de ocupación.', (int) ($threshold * 100)));
            return Command::SUCCESS;
        }

        $io->warning(sprintf('%d curso(s) con baja inscripción (< %d%%):', count($alerts), (int) ($threshold * 100)));
        $io->table(
            ['ID', 'Curso', 'Profesor', 'Horario', 'Inscritos', '% Ocupación'],
            $alerts
        );

        return Command::SUCCESS;
    }
}
