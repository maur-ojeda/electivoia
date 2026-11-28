<?php

namespace App\Tests\Integration;

use App\Entity\School;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Regression test for the comparative matrix Excel export.
 *
 * The controller used Worksheet::setCellValueByColumnAndRow(), which
 * PhpSpreadsheet removed (its own CHANGELOG points to setCellValue()). The call
 * was invisible because phpstan-baseline.neon suppressed the "undefined method"
 * error, so the route returned a 500 for every admin who clicked the export
 * button while PHPStan stayed green.
 *
 * The existing AdminExportTest only covers /admin/export/course/{id}/students.*
 * (a different exporter), so nothing caught this.
 */
class AdminComparativeExportTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
    }

    public function testComparativeMatrixExportReturnsAValidXlsx(): void
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');

        $this->client->loginUser($this->getAdminOf($school), 'main');
        $this->client->request('GET', '/admin/reports/comparative/export');

        $response = $this->client->getResponse();

        $this->assertSame(
            200,
            $response->getStatusCode(),
            'The comparative matrix export must answer 200; a fatal error here means the spreadsheet API changed under the controller.'
        );
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) $response->headers->get('Content-Type'),
            'The export must be served as an xlsx document.'
        );

        $body = $this->captureStreamedBody();

        $this->assertNotSame('', $body, 'The export must produce a body.');
        $this->assertSame(
            "PK\x03\x04",
            substr($body, 0, 4),
            'Excel export must be a valid xlsx (zip) payload.'
        );

        $sheet = $this->loadSheet($body);

        $this->assertSame(
            'Reporte Comparativo',
            $sheet->getTitle(),
            'The workbook must keep the title the report has always used.'
        );
        $this->assertSame(
            'TOTAL',
            (string) $sheet->getCell([1, $sheet->getHighestRow()])->getValue(),
            'The last row must be the totals row, which proves the data rows were written.'
        );
        $this->assertSame(
            'TOTAL',
            (string) $sheet->getCell([\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn()), 1])->getValue(),
            'The header row must end with the TOTAL column.'
        );
        $this->assertNotSame(
            '',
            (string) $sheet->getCell([1, 2])->getValue(),
            'The first data row must start with a grade, not an empty cell.'
        );

        // The TOTAL column of each row must equal the sum of that row's category
        // cells: it proves the coordinate mapping (column, row) stayed correct.
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $sum = 0;
        for ($column = 2; $column < $lastColumn; ++$column) {
            $sum += (int) $sheet->getCell([$column, 2])->getValue();
        }

        $this->assertSame(
            $sum,
            (int) $sheet->getCell([$lastColumn, 2])->getValue(),
            'The TOTAL cell of a row must equal the sum of its category cells.'
        );
    }

    /**
     * StreamedResponse writes to php://output only when sendContent() runs.
     * Grab its callback and execute it inside an output buffer instead.
     */
    private function captureStreamedBody(): string
    {
        $response = $this->client->getResponse();
        $this->assertInstanceOf(
            \Symfony\Component\HttpFoundation\StreamedResponse::class,
            $response,
            'The export must be streamed.'
        );

        $callback = $response->getCallback();
        $this->assertNotNull($callback, 'The streamed export must expose its callback.');

        ob_start();
        $callback();

        return (string) ob_get_clean();
    }

    private function loadSheet(string $body): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'cmp-export-');
        $this->assertNotFalse($path, 'Could not create a scratch file for the xlsx payload.');

        try {
            file_put_contents($path, $body);

            return IOFactory::load($path)->getActiveSheet();
        } finally {
            @unlink($path);
        }
    }

    private function getSchool(string $slug): School
    {
        $school = $this->em->getRepository(School::class)->findOneBy(['slug' => $slug]);
        $this->assertNotNull($school, "Seed school '{$slug}' not found — run app:usability:seed first.");

        return $school;
    }

    private function getAdminOf(School $school): User
    {
        foreach ($this->em->getRepository(User::class)->findBy(['school' => $school]) as $user) {
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $user;
            }
        }

        $this->fail("No ROLE_ADMIN found for school {$school->getName()}.");
    }
}
