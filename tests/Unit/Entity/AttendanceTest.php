<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Attendance;
use PHPUnit\Framework\TestCase;

class AttendanceTest extends TestCase
{
    public function testValidStatusesAreAccepted(): void
    {
        $att = new Attendance();
        foreach (Attendance::VALID_STATUSES as $status) {
            $att->setStatus($status);
            $this->assertSame($status, $att->getStatus());
        }
    }

    public function testInvalidStatusThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/presente/');

        (new Attendance())->setStatus('presente');
    }

    public function testSpanishStatusesAreRejected(): void
    {
        foreach (['presente', 'ausente', 'justificado'] as $bad) {
            try {
                (new Attendance())->setStatus($bad);
                $this->fail("Expected InvalidArgumentException for status '$bad'");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($bad, $e->getMessage());
            }
        }
    }

    public function testValidStatusesConstantContainsExpectedValues(): void
    {
        $this->assertContains('present', Attendance::VALID_STATUSES);
        $this->assertContains('absent', Attendance::VALID_STATUSES);
        $this->assertContains('justified', Attendance::VALID_STATUSES);
        $this->assertContains('late', Attendance::VALID_STATUSES);
        $this->assertCount(4, Attendance::VALID_STATUSES);
    }
}
