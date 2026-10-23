<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagementOverviewService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Platform\{Organization, User};
use App\Modules\ModuleRegistry;
use App\Services\Reporting\Contracts\ManagementKpiSource;
use App\Services\Reporting\Dto\ManagementKpi;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Leitungssicht über alle Bereiche (Feature 184): sammelt die Kennzahlen der
 * Module für einen Betrachter — Seite, PDF und E-Mail nutzen dieselben Zahlen.
 */
final class ManagementOverviewService {
    public const GROUPS = ['finance', 'operations', 'sales', 'service', 'quality'];

    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return array<string, list<ManagementKpi>> Bereich → Kennzahlen, leere Bereiche entfallen */
    public function build(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $sources = array_map(static fn (string $class): ManagementKpiSource => app($class), $this->modules->extensions(ManagementKpiSource::class));
        usort($sources, static fn (ManagementKpiSource $a, ManagementKpiSource $b): int => $a->order() <=> $b->order());

        $groups = array_fill_keys(self::GROUPS, []);
        foreach ($sources as $source) {
            try {
                $groups[$source->group()] = [...($groups[$source->group()] ?? []), ...$source->kpis($viewer, $organization, $from, $to)];
            } catch (Throwable $e) {
                // Eine Quelle darf die Leitungssicht nicht kippen; ihr Fehler wird gemeldet.
                report($e);
            }
        }

        return array_filter($groups, static fn (array $kpis): bool => $kpis !== []);
    }
}
