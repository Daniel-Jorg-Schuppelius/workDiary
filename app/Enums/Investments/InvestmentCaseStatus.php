<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentCaseStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Investments;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand einer Investitionsakte (Feature 069, MVP-200). Ohne
 * Übergangstabelle: Budgetentscheidung, Nachtrag und Abbruch schreiben aus
 * vielen Ständen — geprüft werden die Planungsphase (Antrag), der Abschluss
 * (Nachbewertung) und die Handpflege ({@see self::manualTargets()}).
 */
enum InvestmentCaseStatus: string implements HasLabel {
    use HasOptions;

    case Idea = 'idea';
    case Screening = 'screening';
    case Comparison = 'comparison';
    case BudgetRequest = 'budget_request';
    case InApproval = 'in_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Deferred = 'deferred';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case PostReview = 'post_review';

    /**
     * Vor der Freigabe editierbare Phasen.
     *
     * @return list<self>
     */
    public static function planning(): array {
        return [self::Idea, self::Screening, self::Comparison, self::BudgetRequest];
    }

    /**
     * Von Hand setzbar — Freigabe, Ablehnung, Abbruch und Nachbewertung setzen nur die Dienste.
     * Aus welchem Stand, sagt {@see self::manualTargets()}.
     *
     * @return list<self>
     */
    public static function manual(): array {
        return [...self::planning(), self::InProgress, self::Completed, self::Deferred];
    }

    /**
     * Noch nicht abgeschlossen: zählt als geplante Auszahlung.
     *
     * @return list<self>
     */
    public static function open(): array {
        return [...self::planning(), self::InApproval, self::Approved, self::InProgress];
    }

    public function isPlanning(): bool {
        return in_array($this, self::planning(), true);
    }

    /**
     * Ziele der Handpflege aus diesem Stand: die Planungsphasen untereinander
     * und das Zurückstellen, nach der Freigabe nur der nächste Schritt. In
     * Freigabe, abgelehnt, abgeschlossen, abgebrochen und nachbewertet lässt
     * sich nichts mehr von Hand setzen — eine Ablehnung ist endgültig.
     * Zurückgestellt führt nur in die Phase vor dem Zurückstellen zurück
     * (`$resumeTo`, {@see \App\Models\Investments\InvestmentCase::resumeTarget()}).
     *
     * @return list<self>
     */
    public function manualTargets(self $resumeTo = self::Idea): array {
        $targets = match (true) {
            $this->isPlanning() => [...self::planning(), self::Deferred],
            $this === self::Deferred => [$resumeTo],
            $this === self::Approved => [self::InProgress],
            $this === self::InProgress => [self::Completed],
            default => [],
        };

        return array_values(array_filter($targets, fn (self $target): bool => $target !== $this));
    }

    /** Die Nachbewertung setzt Abschluss oder Abbruch voraus. */
    public function awaitsReview(): bool {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** Lieferanten lassen sich ab der Umsetzung bewerten. */
    public function isRateable(): bool {
        return in_array($this, [self::InProgress, self::Completed, self::PostReview], true);
    }

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Approved, self::Completed => 'success',
            self::Rejected => 'error',
            self::BudgetRequest, self::InApproval => 'warning',
            self::InProgress => 'primary',
            self::PostReview => 'info',
            self::Deferred, self::Cancelled => 'neutral',
            self::Idea, self::Screening, self::Comparison => 'ghost',
        };
    }
}
