<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionScanCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Privacy;

use App\Models\{HelpView, Organization};
use App\Services\Privacy\Retention\RetentionScanService;
use Illuminate\Console\Command;

/**
 * Retention-Scan (Restpunkt 66): erzeugt Lösch-VORSCHLÄGE für
 * fristüberfällige Datensätze je Organisation (Review-Queue,
 * Bestätigung unter Datenschutz → Aufbewahrung). Einzige
 * Direktlöschung: anonyme Hilfe-Aufrufzähler (help_views, 90 Tage —
 * keine Personenbezüge, kein Review nötig).
 */
class RetentionScanCommand extends Command {
    protected $signature = 'privacy:retention-scan';

    protected $description = 'Erzeugt Lösch-Vorschläge für fristüberfällige Datensätze (Review statt Direktlöschung).';

    public function handle(RetentionScanService $scanner): int {
        $proposed = 0;
        $exempt = 0;
        foreach (Organization::query()->get() as $organization) {
            $result = $scanner->scan($organization);
            $proposed += $result['proposed'];
            $exempt += $result['exempt'];
        }

        // Anonyme Telemetrie ohne Review-Bedarf (in-app-hilfe.md: 90 Tage).
        $helpViews = HelpView::query()->where('created_at', '<', now()->subDays(90))->delete();

        $this->info("{$proposed} Vorschlag/Vorschläge erzeugt, {$exempt} Ausnahme(n); {$helpViews} Hilfe-Zähler (>90 Tage) entfernt.");

        return self::SUCCESS;
    }
}
