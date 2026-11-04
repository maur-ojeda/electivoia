<?php

namespace App\Command;

use App\Entity\Attendance;
use App\Entity\Course;
use App\Entity\CourseCategory;
use App\Entity\Enrollment;
use App\Entity\InterestProfile;
use App\Entity\School;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:usability:seed',
    description: 'Seed synthetic personas for usability testing (requires USABILITY_SEED_ENABLED=1)',
)]
class UsabilitySeedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
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

        if ($this->em->getRepository(School::class)->findOneBy(['slug' => 'liceo-ficticio-electivoia'])) {
            $io->error('Already seeded. Run app:usability:reset first.');
            return Command::FAILURE;
        }

        // --- Categories (find-or-create; never deleted by reset) ---
        $catCiencias  = $this->findOrCreateCategory('Ciencias', 'CI');
        $catHistoria  = $this->findOrCreateCategory('Historia, geografía y ciencias sociales', 'HI');
        $catMatematica = $this->findOrCreateCategory('Matemática', 'MA');
        $catArtes     = $this->findOrCreateCategory('Artes', 'AR');

        // --- Schools ---
        $liceo = (new School())
            ->setName('Liceo Ficticio ElectivoIA')
            ->setSlug('liceo-ficticio-electivoia')
            ->setRbd('99000001')
            ->setPlan('premium')
            ->setEnrollmentStart(new \DateTimeImmutable('today'))
            ->setEnrollmentEnd(new \DateTimeImmutable('+30 days'));
        $this->em->persist($liceo);

        $aislamiento = (new School())
            ->setName('Colegio Aislamiento')
            ->setSlug('colegio-aislamiento')
            ->setRbd('99000002')
            ->setPlan('free');
        $this->em->persist($aislamiento);

        // --- Named personas: Liceo Ficticio ---
        $ad1 = $this->makePersona(30100001, 'Carolina Pizarro',  ['ROLE_ADMIN'],        'F', null,  null, $liceo,  'ad1.carolina');
        $ad2 = $this->makePersona(30200001, 'Felipe Navarro',    ['ROLE_ADMIN'],        'M', null,  null, $liceo,  'ad2.felipe');
        $p1  = $this->makePersona(30300001, 'Patricia Vergara',  ['ROLE_TEACHER'],      'F', null,  null, $liceo,  'p1.patricia');
        $p2  = $this->makePersona(30400001, 'Diego Herrera',     ['ROLE_TEACHER'],      'M', null,  null, $liceo,  'p2.diego');
        $pR  = $this->makePersona(30500001, 'Roberto Muñoz',     ['ROLE_TEACHER'],      'M', null,  null, $liceo,  'p3.roberto');
        $p4  = $this->makePersona(30500002, 'Alejandra Soto',    ['ROLE_TEACHER'],      'F', null,  null, $liceo,  'p4.alejandra');
        $p5  = $this->makePersona(30500003, 'Martín Espinoza',   ['ROLE_TEACHER'],      'M', null,  null, $liceo,  'p5.martin');
        $e1  = $this->makePersona(30600001, 'Catalina Rojas',    ['ROLE_STUDENT'],      'F', '1M',  null, $liceo,  'e1.catalina');
        $e2  = $this->makePersona(30700001, 'Matías Fuentes',    ['ROLE_STUDENT'],      'M', '3M',  5.1,  $liceo,  'e2.matias');
        $e3  = $this->makePersona(30800001, 'Josefa Muñoz',      ['ROLE_STUDENT'],      'F', '2M',  6.1,  $liceo,  'e3.josefa');
        $e4  = $this->makePersona(30900001, 'Tomás Araya',       ['ROLE_STUDENT'],      'M', '4M',  5.5,  $liceo,  'e4.tomas');
        $e5  = $this->makePersona(31000001, 'Valentina Díaz',    ['ROLE_STUDENT'],      'F', '1M',  null, $liceo,  'e5.valentina');
        $eE  = $this->makePersona(31050001, 'Relleno Empate',    ['ROLE_STUDENT'],      'M', '3M',  5.1,  $liceo,  'relleno.empate');
        $a1  = $this->makePersona(31100001, 'Rosa Contreras',    ['ROLE_GUARDIAN'],     'F', null,  null, $liceo,  'a1.rosa');
        $a2  = $this->makePersona(31200001, 'Andrés Lagos',      ['ROLE_GUARDIAN'],     'M', null,  null, $liceo,  'a2.andres');
        $ben = $this->makePersona(31300001, 'Benjamín Lagos',    ['ROLE_STUDENT'],      'M', '4M',  6.1,  $liceo,  'benjamin.lagos');
        $sa1 = $this->makePersona(31400001, 'Ignacia Torres',    ['ROLE_SUPER_ADMIN'],  'F', null,  null, null,    'sa1.ignacia');

        // --- Named personas: Colegio Aislamiento ---
        $aAdmin = $this->makePersona(32100001, 'Admin Aislamiento',    ['ROLE_ADMIN'],   'M', null, null, $aislamiento, 'admin.aislamiento');
        $aProf  = $this->makePersona(32200001, 'Profesor Aislamiento', ['ROLE_TEACHER'], 'M', null, null, $aislamiento, 'prof.aislamiento');

        // --- Relleno: Liceo Ficticio (31_500_001 onwards) ---
        // Groups: Física(0-28), Química(29-58), Cálculo(59-63), CienciasSalud(64-93), Comprensión(94-128), Biología(129-143), Artes(144-155)
        $relleno = [];
        for ($i = 0; $i < 156; $i++) {
            $base  = 31500001 + $i;
            $grade = ($i % 2 === 0) ? '4M' : '3M';
            $avg   = $this->rellenoAvg($i);
            $relleno[$i] = $this->makeRelleno($base, $avg, $grade, $liceo);
        }

        // --- Relleno: Colegio Aislamiento ---
        $aisEst = [];
        for ($i = 0; $i < 5; $i++) {
            $aisEst[$i] = $this->makePersona(
                32300001 + $i,
                'Estudiante Aislamiento ' . ($i + 1),
                ['ROLE_STUDENT'],
                ($i % 2 === 0) ? 'M' : 'F',
                '4M',
                round(5.0 + $i * 0.3, 1),
                $aislamiento,
                'est.aislamiento.' . ($i + 1)
            );
        }

        $allGrades = ['1M', '2M', '3M', '4M'];

        // --- Courses: Liceo Ficticio ---
        $cFisica = $this->makeCourse('Física', 30, $catCiencias, $pR, $liceo, $allGrades);
        $cFisica->setDescription('Exploración de mecánica, termodinámica y ondas a través de experimentos y problemas aplicados al mundo real.');
        $cFisica->setSchedule('Martes y jueves 10:00–11:30');

        $cQuimica = $this->makeCourse('Química', 30, $catCiencias, $pR, $liceo, $allGrades);
        $cQuimica->setDescription('Estudio de la materia, sus transformaciones y aplicaciones en la industria y el medioambiente.');
        $cQuimica->setSchedule('Lunes y miércoles 08:30–10:00');

        $cCalculo = $this->makeCourse('Cálculo', 30, $catMatematica, $p4, $liceo, $allGrades);
        $cCalculo->setDescription('Introducción a límites, derivadas e integrales con aplicaciones en ciencias e ingeniería.');
        $cCalculo->setSchedule('Miércoles y viernes 14:00–15:30');

        $cCiencias = $this->makeCourse('Ciencias de la Salud', 30, $catCiencias, $p2, $liceo, $allGrades);
        $cCiencias->setDescription('Fundamentos de biología humana, salud pública y primeros auxilios básicos para la vida cotidiana.');
        $cCiencias->setSchedule('Lunes, miércoles y viernes 11:00–12:00');

        $cHistoria = $this->makeCourse('Comprensión Histórica del Presente', 40, $catHistoria, $p1, $liceo, $allGrades);
        $cHistoria->setDescription('Análisis de procesos históricos del Chile contemporáneo y su conexión con el presente.');
        $cHistoria->setSchedule('Martes y jueves 08:30–10:00');

        $cBiologia = $this->makeCourse('Biología de los Ecosistemas', 25, $catCiencias, $p4, $liceo, $allGrades);
        $cBiologia->setDescription('Ecología, biodiversidad y crisis medioambiental: explorando los ecosistemas de Chile.');
        $cBiologia->setSchedule('Lunes y miércoles 15:30–17:00');

        $cArtes = $this->makeCourse('Artes Visuales, Audiovisuales y Multimediales', 20, $catArtes, $p5, $liceo, $allGrades);
        $cArtes->setDescription('Creación de proyectos visuales, audiovisuales y multimediales integrando técnicas análogas y digitales.');
        $cArtes->setSchedule('Viernes 14:00–17:00');

        // --- Course: Colegio Aislamiento ---
        $cAislamiento = $this->makeCourse('Electivo Aislamiento', 10, null, $aProf, $aislamiento, $allGrades);

        // --- Enrollments: Liceo Ficticio ---
        // Física: 29 relleno (0-28) + E2
        for ($i = 0; $i < 29; $i++) {
            $this->enroll($relleno[$i], $cFisica);
        }
        $this->enroll($e2, $cFisica);

        // Química: 30 relleno (29-58)
        for ($i = 29; $i < 59; $i++) {
            $this->enroll($relleno[$i], $cQuimica);
        }

        // Cálculo: 5 relleno (59-63) + E4
        for ($i = 59; $i < 64; $i++) {
            $this->enroll($relleno[$i], $cCalculo);
        }
        $this->enroll($e4, $cCalculo);

        // Ciencias de la Salud: 30 relleno (64-93)
        for ($i = 64; $i < 94; $i++) {
            $this->enroll($relleno[$i], $cCiencias);
        }

        // Comprensión Histórica: 35 relleno (94-128) + E2
        for ($i = 94; $i < 129; $i++) {
            $this->enroll($relleno[$i], $cHistoria);
        }
        $this->enroll($e2, $cHistoria);

        // Biología: 15 relleno (129-143)
        for ($i = 129; $i < 144; $i++) {
            $this->enroll($relleno[$i], $cBiologia);
        }

        // Artes: 12 relleno (144-155)
        for ($i = 144; $i < 156; $i++) {
            $this->enroll($relleno[$i], $cArtes);
        }

        // --- Enrollments: Colegio Aislamiento ---
        foreach ($aisEst as $est) {
            $this->enroll($est, $cAislamiento);
        }

        // --- Sync currentEnrollment ---
        $cFisica->setCurrentEnrollment(30);
        $cQuimica->setCurrentEnrollment(30);
        $cCalculo->setCurrentEnrollment(6);
        $cCiencias->setCurrentEnrollment(30);
        $cHistoria->setCurrentEnrollment(36);
        $cBiologia->setCurrentEnrollment(15);
        $cArtes->setCurrentEnrollment(12);
        $cAislamiento->setCurrentEnrollment(5);

        // --- Attendance: Comprensión Histórica — E2 (20 sessions: 15 present, 3 absent, 2 justified) ---
        $statuses = array_merge(array_fill(0, 15, 'presente'), array_fill(0, 3, 'ausente'), array_fill(0, 2, 'justificado'));
        $this->generateAttendance($e2, $cHistoria, $statuses);

        // --- Attendance: Ciencias de la Salud — 5 students <85%, rest ~90% ---
        for ($i = 64; $i < 94; $i++) {
            if ($i < 69) {
                // 15/20 = 75%
                $s = array_merge(array_fill(0, 15, 'presente'), array_fill(0, 5, 'ausente'));
            } else {
                // 18/20 = 90%
                $s = array_merge(array_fill(0, 18, 'presente'), array_fill(0, 2, 'ausente'));
            }
            $this->generateAttendance($relleno[$i], $cCiencias, $s);
        }

        // --- InterestProfile: E3 ---
        $profile = new InterestProfile();
        $profile->setStudent($e3);
        $profile->setInterests([
            'Artes'                                      => 4,
            'Ciencias'                                   => 4,
            'Filosofía'                                  => 2,
            'Historia, geografía y ciencias sociales'    => 2,
            'Lengua y literatura'                        => 2,
            'Matemática'                                 => 1,
            'Educación física y salud'                   => 1,
        ]);
        $this->em->persist($profile);

        // --- Guardian relationships ---
        $a1->addGuardianStudent($e2);
        $a2->addGuardianStudent($e3);
        $a2->addGuardianStudent($ben);

        $this->em->flush();

        // --- Output credentials ---
        $creds = $this->buildCredentials();
        $io->section('Seed completed. Credentials summary:');
        $io->table(
            ['ID', 'RUT', 'Password', 'Email', 'Role'],
            $creds
        );
        $io->note('Save credentials to staging/test-credentials.json manually or redirect output.');

        return Command::SUCCESS;
    }

    // -------------------------------------------------------------------------

    private function findOrCreateCategory(string $name, string $area): CourseCategory
    {
        $cat = $this->em->getRepository(CourseCategory::class)->findOneBy(['name' => $name]);
        if ($cat) {
            return $cat;
        }
        $cat = (new CourseCategory())->setName($name)->setArea($area);
        $this->em->persist($cat);
        return $cat;
    }

    private function makePersona(
        int $rutBase,
        string $name,
        array $roles,
        string $gender,
        ?string $grade,
        ?float $avg,
        ?School $school,
        string $emailPrefix,
    ): User {
        $rut = self::rut($rutBase);
        $pwd = substr((string)$rutBase, 0, 6);

        $user = new User();
        $user->setRut($rut);
        $user->setFullName($name);
        $user->setRoles($roles);
        $user->setGender($gender);
        $user->setActive(true);
        $user->setEmail($emailPrefix . '@electivoia.test');
        if ($grade) {
            $user->setGrade($grade);
        }
        if ($avg !== null) {
            $user->setAverageGrade($avg);
        }
        $user->setSchool($school);
        $user->setPassword($this->hasher->hashPassword($user, $pwd));
        $this->em->persist($user);
        return $user;
    }

    private function makeRelleno(int $base, ?float $avg, string $grade, School $school): User
    {
        $rut = self::rut($base);
        $user = new User();
        $user->setRut($rut);
        $user->setFullName('Relleno ' . number_format($base, 0, '', '.'));
        $user->setGrade($grade);
        $user->setRoles(['ROLE_STUDENT']);
        $user->setGender($base % 2 === 0 ? 'F' : 'M');
        $user->setActive(true);
        $user->setSchool($school);
        $user->setEmail('relleno.' . $base . '@electivoia.test');
        if ($avg !== null) {
            $user->setAverageGrade($avg);
        }
        $user->setPassword($this->hasher->hashPassword($user, substr((string)$base, 0, 6)));
        $this->em->persist($user);
        return $user;
    }

    private function makeCourse(string $name, int $cap, ?CourseCategory $cat, User $teacher, School $school, array $targetGrades = []): Course
    {
        $course = new Course();
        $course->setName($name);
        $course->setMaxCapacity($cap);
        $course->setIsActive(true);
        $course->setTeacher($teacher);
        $course->setSchool($school);
        if ($cat) {
            $course->setCategory($cat);
        }
        if (!empty($targetGrades)) {
            $course->setTargetGrades($targetGrades);
        }
        $this->em->persist($course);
        return $course;
    }

    private function enroll(User $student, Course $course): void
    {
        $enrollment = new Enrollment();
        $enrollment->setStudent($student);
        $enrollment->setCourse($course);
        $enrollment->setEnrolledAt(new \DateTime('-' . rand(1, 20) . ' days'));
        $this->em->persist($enrollment);
    }

    private function generateAttendance(User $student, Course $course, array $statuses): void
    {
        foreach (array_values($statuses) as $i => $status) {
            $att = new Attendance();
            $att->setStudent($student);
            $att->setCourse($course);
            $att->setDate(new \DateTime('-' . ($i + 1) . ' days'));
            $att->setStatus($status);
            $this->em->persist($att);
        }
    }

    private function rellenoAvg(int $i): float
    {
        if ($i < 29) {
            // Física: avg >= 5.2
            return round(5.2 + ($i % 18) * 0.1, 1);
        }
        return round(5.0 + (($i * 7) % 20) * 0.1, 1);
    }

    private function buildCredentials(): array
    {
        $rows = [
            ['AD1', self::rut(30100001), '301000', 'ad1.carolina@electivoia.test',   'ADMIN (Liceo Ficticio)'],
            ['AD2', self::rut(30200001), '302000', 'ad2.felipe@electivoia.test',     'ADMIN (Liceo Ficticio)'],
            ['P1',  self::rut(30300001), '303000', 'p1.patricia@electivoia.test',    'TEACHER (Comprensión Histórica)'],
            ['P2',  self::rut(30400001), '304000', 'p2.diego@electivoia.test',       'TEACHER (Ciencias de la Salud)'],
            ['E1',  self::rut(30600001), '306000', 'e1.catalina@electivoia.test',    'STUDENT (sin inscripciones)'],
            ['E2',  self::rut(30700001), '307000', 'e2.matias@electivoia.test',      'STUDENT (Física+Comprensión)'],
            ['E3',  self::rut(30800001), '308000', 'e3.josefa@electivoia.test',      'STUDENT (perfil intereses)'],
            ['E4',  self::rut(30900001), '309000', 'e4.tomas@electivoia.test',       'STUDENT (Cálculo)'],
            ['E5',  self::rut(31000001), '310000', 'e5.valentina@electivoia.test',   'STUDENT (sin inscripciones)'],
            ['A1',  self::rut(31100001), '311000', 'a1.rosa@electivoia.test',        'GUARDIAN (pupilo: E2)'],
            ['A2',  self::rut(31200001), '312000', 'a2.andres@electivoia.test',      'GUARDIAN (pupilos: E3+Benjamín)'],
            ['SA1', self::rut(31400001), '314000', 'sa1.ignacia@electivoia.test',    'SUPER_ADMIN (sin colegio)'],
            ['AdminAisl', self::rut(32100001), '321000', 'admin.aislamiento@electivoia.test', 'ADMIN (Aislamiento)'],
        ];
        return $rows;
    }

    // -------------------------------------------------------------------------

    private static function dv(int $n): string
    {
        $sum = 0;
        $factor = 2;
        while ($n > 0) {
            $sum += ($n % 10) * $factor;
            $n = intdiv($n, 10);
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $remainder = 11 - ($sum % 11);
        return match ($remainder) {
            11 => '0',
            10 => 'K',
            default => (string) $remainder,
        };
    }

    private static function rut(int $n): string
    {
        return number_format($n, 0, '', '.') . '-' . self::dv($n);
    }
}
