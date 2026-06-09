<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingDeadlineService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Models\Whistleblowing\WhistleblowingCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ermittelt faellige Fristen (Abschnitt 15): ueberfaellige Eingangsbestaetigung
 * und ueberfaellige/anstehende Rueckmeldung. Liefert nur Referenzen (kein
 * Meldeinhalt). Org-uebergreifend (Cron-Kontext, kein Org-Scope).
 *
 * @phpstan-type Reminder array{case: WhistleblowingCase, kind: string}
 */
class WhistleblowingDeadlineService {
    /** @return Collection<int, array{case: WhistleblowingCase, kind: string}> */
    public function dueReminders(?Carbon $now = null): Collection {
        $now ??= Carbon::now();

        $acknowledge = WhistleblowingCase::withoutGlobalScopes()
            ->where('status', 'submitted')
            ->whereNull('acknowledged_at')
            ->whereNotNull('acknowledgement_due_at')
            ->where('acknowledgement_due_at', '<=', $now)
            ->get()
            ->map(fn(WhistleblowingCase $c) => ['case' => $c, 'kind' => 'acknowledge']);

        $closedish = ['closed_substantiated', 'closed_unsubstantiated', 'closed_out_of_scope',
            'closed_duplicate', 'retention_review', 'legal_hold', 'deleted'];

        $feedback = WhistleblowingCase::withoutGlobalScopes()
            ->whereNotIn('status', $closedish)
            ->whereNull('feedback_sent_at')
            ->whereNotNull('feedback_due_at')
            ->where('feedback_due_at', '<=', $now)
            ->get()
            ->map(fn(WhistleblowingCase $c) => ['case' => $c, 'kind' => 'feedback']);

        return $acknowledge->concat($feedback)->values();
    }
}
