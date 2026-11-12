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
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Support\{Carbon, Collection};

/**
 * Ermittelt faellige Fristen (Abschnitt 15) in Stufen: Vorwarnung 48 Stunden vor
 * der Eingangsbestaetigung, 14 Tage und 48 Stunden vor der Rueckmeldung, danach
 * ueberfaellig; dazu das Pruefdatum einer Loeschsperre. Je Frist nur die
 * dringendste Stufe. Liefert nur Referenzen (kein Meldeinhalt).
 * Org-uebergreifend (Cron-Kontext, kein Org-Scope).
 *
 * @phpstan-type Reminder array{case: WhistleblowingCase, kind: string}
 */
class WhistleblowingDeadlineService {
    public const string ACKNOWLEDGE_SOON = 'acknowledge_48h';

    public const string ACKNOWLEDGE_OVERDUE = 'acknowledge';

    public const string FEEDBACK_14_DAYS = 'feedback_14d';

    public const string FEEDBACK_SOON = 'feedback_48h';

    public const string FEEDBACK_OVERDUE = 'feedback';

    public const string LEGAL_HOLD_REVIEW = 'legal_hold';

    /** Vorwarnstufen gehen je Fall genau einmal hinaus, überfällige Fristen täglich. */
    public const array ONCE = [self::ACKNOWLEDGE_SOON, self::FEEDBACK_14_DAYS, self::FEEDBACK_SOON];

    private const array FEEDBACK_ENDED = ['closed_substantiated', 'closed_unsubstantiated', 'closed_out_of_scope',
        'closed_duplicate', 'retention_review', 'legal_hold', 'deleted'];

    /** @return Collection<int, array{case: WhistleblowingCase, kind: string}> */
    public function dueReminders(?Carbon $now = null): Collection {
        $now ??= Carbon::now();
        $soon = $now->copy()->addHours(48);

        $acknowledge = WhistleblowingCase::withoutGlobalScopes()
            ->where('status', 'submitted')
            ->whereNull('acknowledged_at')
            ->whereNotNull('acknowledgement_due_at')
            ->where('acknowledgement_due_at', '<=', $soon)
            ->get()
            ->map(fn (WhistleblowingCase $c) => [
                'case' => $c,
                'kind' => $c->acknowledgement_due_at?->lessThanOrEqualTo($now) === false ? self::ACKNOWLEDGE_SOON : self::ACKNOWLEDGE_OVERDUE,
            ]);

        $feedback = WhistleblowingCase::withoutGlobalScopes()
            ->whereNotIn('status', self::FEEDBACK_ENDED)
            ->whereNull('feedback_sent_at')
            ->whereNotNull('feedback_due_at')
            ->where('feedback_due_at', '<=', $now->copy()->addDays(14))
            ->get()
            ->map(fn (WhistleblowingCase $c) => [
                'case' => $c,
                'kind' => match (true) {
                    $c->feedback_due_at?->lessThanOrEqualTo($now) !== false => self::FEEDBACK_OVERDUE,
                    $c->feedback_due_at->lessThanOrEqualTo($soon) => self::FEEDBACK_SOON,
                    default => self::FEEDBACK_14_DAYS,
                },
            ]);

        $legalHold = WhistleblowingCase::withoutGlobalScopes()
            ->where('status', 'legal_hold')
            ->whereNotNull('legal_hold_review_at')
            ->where('legal_hold_review_at', '<', DateRange::dayAfter($now))
            ->get()
            ->map(fn (WhistleblowingCase $c) => ['case' => $c, 'kind' => self::LEGAL_HOLD_REVIEW]);

        return $acknowledge->concat($feedback)->concat($legalHold)->values();
    }

    public function dueAt(WhistleblowingCase $case, string $kind): ?CarbonInterface {
        return match ($kind) {
            self::ACKNOWLEDGE_SOON, self::ACKNOWLEDGE_OVERDUE => $case->acknowledgement_due_at,
            self::LEGAL_HOLD_REVIEW => $case->legal_hold_review_at,
            default => $case->feedback_due_at,
        };
    }
}
