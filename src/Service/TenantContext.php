<?php

namespace App\Service;

use App\Entity\School;

/**
 * Holds the School active for the current request.
 *
 * Set by TenantSubscriber on every authenticated request.
 * Sprint 5: nullable — code can run without a tenant (single-tenant mode).
 * Sprint 6: enforce non-null once all queries filter by school.
 */
class TenantContext
{
    private ?School $currentSchool = null;

    public function setCurrentSchool(School $school): void
    {
        $this->currentSchool = $school;
    }

    public function getCurrentSchool(): ?School
    {
        return $this->currentSchool;
    }

    public function hasSchool(): bool
    {
        return $this->currentSchool !== null;
    }

    public function reset(): void
    {
        $this->currentSchool = null;
    }
}
