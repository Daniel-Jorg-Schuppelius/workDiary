<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiagnosticsReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Diagnostics;

use Carbon\CarbonImmutable;

final class DiagnosticsReport {
    /** @param  list<DiagnosticSection>  $sections */
    public function __construct(
        public readonly array $sections,
        public readonly CarbonImmutable $generatedAt,
    ) {}

    public function overallStatus(): DiagnosticStatus {
        return DiagnosticStatus::worst(
            ...array_map(static fn(DiagnosticSection $s): DiagnosticStatus => $s->status, $this->sections)
        );
    }

    public function section(string $code): ?DiagnosticSection {
        foreach ($this->sections as $section) {
            if ($section->code === $code) {
                return $section;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'generated_at' => $this->generatedAt->toIso8601String(),
            'overall_status' => $this->overallStatus()->value,
            'sections' => array_map(static fn(DiagnosticSection $s): array => $s->toArray(), $this->sections),
        ];
    }
}
