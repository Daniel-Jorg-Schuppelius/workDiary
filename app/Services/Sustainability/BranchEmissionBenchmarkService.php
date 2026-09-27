<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BranchEmissionBenchmarkService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sustainability;

use App\Models\Platform\Organization;
use App\Support\OrganizationContext;
use CommonToolkit\Helper\FileSystem\File;

/**
 * Branchenvergleich der Emissionen (MVP-949) als anonymes Plattform-Aggregat:
 * je Branchenprofil Anzahl Organisationen, Mittel und Median der Jahres-
 * emission — nur Gruppen ab MIN_ORGANIZATIONS, keine Einzelwerte.
 */
final class BranchEmissionBenchmarkService {
    public const MIN_ORGANIZATIONS = 3;

    public function __construct(private readonly EmissionCalculationService $emissions) {}

    /** @return list<array{branch: string, organizations: int, mean_t: float, median_t: float}> */
    public function benchmark(int $year): array {
        $totals = [];
        foreach (Organization::query()->withoutGlobalScopes()->where('is_demo', false)->get() as $organization) {
            $branch = $organization->primaryBranchProfileCode();
            if ($branch === null) {
                continue;
            }
            $kg = (float) OrganizationContext::run($organization, fn (): float => $this->emissions->aggregate((int) $organization->id, $year . '-01-01', $year . '-12-31')['co2e_total_kg']);
            if ($kg > 0.0) {
                $totals[$branch][] = $kg / 1000;
            }
        }
        $rows = [];
        foreach ($totals as $branch => $values) {
            if (count($values) < self::MIN_ORGANIZATIONS) {
                continue;
            }
            sort($values);
            $n = count($values);
            $median = $n % 2 === 1 ? $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
            $rows[] = ['branch' => $this->branchLabel((string) $branch), 'organizations' => $n, 'mean_t' => round(array_sum($values) / $n, 1), 'median_t' => round($median, 1)];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp($a['branch'], $b['branch']));

        return $rows;
    }

    /** Bezeichnung des mitgelieferten Profils, sonst der Code. */
    private function branchLabel(string $code): string {
        $file = database_path('data/branchprofiles/' . basename($code) . '.php');
        if (! File::isFile($file)) {
            return $code;
        }
        /** @var array<string, mixed> $profile */
        $profile = require $file;

        return (string) ($profile['label'] ?? $code);
    }
}
