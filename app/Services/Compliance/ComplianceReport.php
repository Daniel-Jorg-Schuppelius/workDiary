<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

/**
 * Ergebnis einer Compliance-Prüfung für eine Schicht.
 */
final class ComplianceReport
{
    /** @param list<ComplianceViolation> $violations */
    public function __construct(public readonly array $violations = []) {}

    public function hasViolations(): bool
    {
        return $this->violations !== [];
    }

    public function hasErrors(): bool
    {
        foreach ($this->violations as $v) {
            if ($v->severity === ComplianceViolation::SEVERITY_ERROR) {
                return true;
            }
        }

        return false;
    }

    /** @return list<ComplianceViolation> */
    public function bySeverity(string $severity): array
    {
        return array_values(array_filter(
            $this->violations,
            static fn (ComplianceViolation $v): bool => $v->severity === $severity,
        ));
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(
            static fn (ComplianceViolation $v): array => $v->toArray(),
            $this->violations,
        );
    }
}
