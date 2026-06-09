<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReviewRetention.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Whistleblowing;

use App\Enums\Whistleblowing\CaseStatus;
use App\Models\Whistleblowing\WhistleblowingCase;
use App\Services\Whistleblowing\WhistleblowingCaseWorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Ueberfuehrt abgeschlossene Faelle, deren Aufbewahrungsfrist abgelaufen ist, in
 * die Aufbewahrungspruefung (retention_review). BEWUSST keine automatische
 * Loeschung – die Loeschung ist eine bewusst autorisierte Handlung (Abschnitt 16).
 */
class ReviewRetention extends Command {
    protected $signature = 'whistleblowing:retention-review';

    protected $description = 'Setzt faellige, abgeschlossene Faelle auf Aufbewahrungspruefung (keine Auto-Loeschung).';

    private const CLOSED = [
        'closed_substantiated', 'closed_unsubstantiated', 'closed_out_of_scope', 'closed_duplicate',
    ];

    public function handle(WhistleblowingCaseWorkflowService $workflow): int {
        $moved = 0;

        WhistleblowingCase::withoutGlobalScopes()
            ->whereIn('status', self::CLOSED)
            ->whereNotNull('retention_due_at')
            ->where('retention_due_at', '<=', Carbon::now())
            ->each(function (WhistleblowingCase $case) use ($workflow, &$moved): void {
                $workflow->transition($case, CaseStatus::RetentionReview);
                $moved++;
            });

        $this->info("Aufbewahrungspruefung: {$moved} Faelle ueberfuehrt.");

        return self::SUCCESS;
    }
}
