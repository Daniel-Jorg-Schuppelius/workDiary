<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScanComplianceFindingsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Compliance;

use App\Models\Organization;
use App\Services\Compliance\{ComplianceFindingRecorder, ComplianceScanService};
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Persistiert die ArbZG-Ist-Verstöße (Feature 006, Welle D) revisionssicher:
 * scannt je Organisation über ein festes Fenster (Default 90 Tage) mit der
 * Report-Logik ({@see ComplianceScanService}) und speichert sie über den
 * {@see ComplianceFindingRecorder} (Dedup, Auto-„behoben", Audit).
 *
 * Festes (statt nutzer-gefiltertes) Fenster, weil sonst Auto-„behoben" nicht
 * von „ausgefiltert" unterscheidbar wäre.
 */
class ScanComplianceFindingsCommand extends Command {
    protected $signature = 'compliance:scan-findings
        {--days=90 : Rückblick-Fenster in Tagen}';

    protected $description = 'Persistiert erkannte ArbZG-Compliance-Verstöße je Organisation (Dedup, Auto-„behoben", Audit).';

    public function handle(ComplianceScanService $scanner, ComplianceFindingRecorder $recorder): int {
        $days = max(1, (int) $this->option('days'));
        $to = CarbonImmutable::now();
        $from = $to->subDays($days)->startOfDay();

        $totals = ['created' => 0, 'updated' => 0, 'reopened' => 0, 'resolved' => 0];

        // Vorherige Org-Bindung sichern/wiederherstellen (Test/Queue-Kontext); je
        // Iteration neu binden, damit Zeitzone/Tagesgrenzen der Report-Ansicht entsprechen.
        $bound = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $previous = $bound instanceof Organization ? $bound : null;

        try {
            $organizationIds = Organization::query()->orderBy('id')->pluck('id');
            foreach ($organizationIds as $organizationId) {
                $organization = Organization::query()->whereKey($organizationId)->first();
                if ($organization === null) {
                    continue;
                }
                app()->instance('currentOrganization', $organization);
                $findingsByUser = $scanner->findingsForRange($organization, $from, $to);
                $stats = $recorder->record($organization, $from, $to, $findingsByUser);
                foreach ($stats as $k => $v) {
                    $totals[$k] += $v;
                }
            }
        } finally {
            if ($previous !== null) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }

        $this->info(sprintf(
            'Compliance-Scan: %d neu, %d aktualisiert, %d reaktiviert, %d behoben.',
            $totals['created'],
            $totals['updated'],
            $totals['reopened'],
            $totals['resolved'],
        ));

        return self::SUCCESS;
    }
}
