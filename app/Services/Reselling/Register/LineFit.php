<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LineFit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\PeriodStatus;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Services\Reselling\Mirror\MirrorLine;

/**
 * Zeitliche Passung einer Rechnungsposition zu einer Periode (Phase 166,
 * MVP-1448). Maß ist der Abstand des Periodenbeginns zum nächsten
 * Zyklusbeginn des Leistungszeitraums (Leistungsbeginn plus k × Periodenlänge),
 * ohne Leistungszeitraum zum Rechnungsdatum. Liegt eine andere offene Periode
 * desselben Produkts beim Empfänger deutlich näher, gehört die Position eher
 * dorthin. Überschneidung, außerhalb und „eher woanders“ verlangen beim
 * Zuordnen eine Bestätigung (Entscheidung des Inhabers 2026-10-31).
 */
final class LineFit {
    public const EXACT = 'exact';

    public const SHIFTED = 'shifted';

    public const OVERLAP = 'overlap';

    /** Ohne Leistungszeitraum: Rechnungsdatum weit vom Periodenbeginn. */
    public const FAR = 'far';

    public const OUTSIDE = 'outside';

    public const ELSEWHERE = 'elsewhere';

    public const UNKNOWN = 'unknown';

    /** Eine andere Periode gilt erst als besser, wenn sie mindestens so viele Tage näher liegt. */
    private const BETTER_MARGIN_DAYS = 7;

    private const RANK = [self::EXACT => 0, self::SHIFTED => 1, self::UNKNOWN => 2, self::FAR => 3, self::OVERLAP => 3, self::ELSEWHERE => 4, self::OUTSIDE => 5];

    private function __construct(
        public readonly string $kind,
        public readonly ?int $days,
        public readonly ?ResalePeriod $better = null,
    ) {}

    /**
     * @param  iterable<ResalePeriod>  $competitors  andere offene Perioden desselben Produkts beim Empfänger
     */
    public static function of(MirrorLine $line, ResalePeriod $period, iterable $competitors = []): self {
        $days = self::offset($line, $period);
        if ($days === null) {
            return new self(self::UNKNOWN, null);
        }
        if (! LinkProposer::serviceOverlaps($period, $line)) {
            return new self(self::OUTSIDE, $days);
        }
        // Mehrjahres- oder Sammelrechnung, deren Leistungszeitraum die Periode ganz umfasst: passt,
        // auch wenn ihr Zyklus nicht am Periodenbeginn liegt (Phase 168, MVP-1459 — 34 Domain-Fehlalarme auf Prod).
        if (self::covers($line, $period)) {
            return new self(self::EXACT, $days);
        }
        $kind = match (true) {
            $days <= LinkProposer::SERVICE_TOLERANCE_DAYS => self::EXACT,
            $days <= LinkProposer::WINDOW_BEFORE => self::SHIFTED,
            $line->serviceFrom === null || $line->serviceTo === null => self::FAR,
            default => self::OVERLAP,
        };
        if ($kind === self::EXACT) {
            return new self($kind, $days);
        }
        $better = null;
        $betterDays = null;
        foreach ($competitors as $other) {
            if ($other->id === $period->id) {
                continue;
            }
            $otherDays = self::offset($line, $other);
            if ($otherDays === null || $otherDays > LinkProposer::WINDOW_BEFORE || $otherDays + self::BETTER_MARGIN_DAYS > $days || ! LinkProposer::serviceOverlaps($other, $line)) {
                continue;
            }
            if ($betterDays === null || $otherDays < $betterDays) {
                $better = $other;
                $betterDays = $otherDays;
            }
        }

        return $better !== null ? new self(self::ELSEWHERE, $days, $better) : new self($kind, $days);
    }

    /** Wie {@see of()}, die Vergleichsperioden lädt die Methode selbst (Prüfung beim Speichern). */
    public static function forPeriod(MirrorLine $line, ResalePeriod $period): self {
        return self::of($line, $period, self::competitorsOf($period));
    }

    /**
     * Offene und teilweise gedeckte Perioden aller Abos desselben Produkts, die an
     * denselben Rechnungsempfänger gehen — ohne die Periode selbst.
     *
     * @return list<ResalePeriod>
     */
    public static function competitorsOf(ResalePeriod $period): array {
        $subscription = $period->subscription;
        $billedTo = $subscription->billedTo();
        if ($billedTo === null) {
            return [];
        }
        $product = $subscription->productKey();
        $competitors = [];
        $subscriptions = ResaleSubscription::query()->forCustomer($billedTo)->where('is_own_holding', false)
            ->with(['periods' => static fn ($q) => $q->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])])
            ->get();
        foreach ($subscriptions as $candidate) {
            if ($candidate->productKey() !== $product) {
                continue;
            }
            foreach ($candidate->periods as $other) {
                if ($other->id !== $period->id) {
                    $competitors[] = $other;
                }
            }
        }

        return $competitors;
    }

    /**
     * Tage zwischen Periodenbeginn und dem nächsten Zyklusbeginn der Position;
     * ohne Leistungszeitraum zum Rechnungsdatum, ohne beides null.
     */
    public static function offset(MirrorLine $line, ResalePeriod $period): ?int {
        $start = $period->starts_on;
        if ($line->serviceFrom !== null && $line->serviceTo !== null) {
            $term = max(1, $period->termMonths());
            $best = null;
            for ($cycle = $line->serviceFrom; ! $cycle->greaterThan($line->serviceTo); $cycle = $cycle->addMonthsNoOverflow($term)) {
                $days = (int) abs($cycle->diffInDays($start));
                $best = $best === null ? $days : min($best, $days);
                if ($cycle->greaterThan($start)) {
                    break; // jeder weitere Zyklus liegt noch weiter weg
                }
            }

            return $best;
        }
        $date = LicenseMonths::referenceDate($line);

        return $date === null ? null : (int) abs($date->diffInDays($start));
    }

    /** Umfasst der Leistungszeitraum die ganze Periode (Toleranz wie beim Zyklus)? */
    public static function covers(MirrorLine $line, ResalePeriod $period): bool {
        if ($line->serviceFrom === null || $line->serviceTo === null) {
            return false;
        }

        return ! $line->serviceFrom->greaterThan($period->starts_on->addDays(LinkProposer::SERVICE_TOLERANCE_DAYS))
            && ! $line->serviceTo->lessThan($period->ends_on->subDays(LinkProposer::SERVICE_TOLERANCE_DAYS));
    }

    public function needsConfirmation(): bool {
        return in_array($this->kind, [self::FAR, self::OVERLAP, self::OUTSIDE, self::ELSEWHERE], true);
    }

    /** Reihenfolge der Kandidaten: passend vor versetzt vor fraglich. */
    public function rank(): int {
        return self::RANK[$this->kind];
    }

    public function label(): string {
        return match ($this->kind) {
            self::UNKNOWN => '',
            self::SHIFTED, self::FAR => (string) __('resale.fit.' . $this->kind, ['days' => $this->days]),
            self::ELSEWHERE => (string) __('resale.fit.elsewhere', ['period' => $this->better?->label() ?? '—']),
            default => (string) __('resale.fit.' . $this->kind),
        };
    }

    public function tone(): string {
        return match ($this->kind) {
            self::EXACT => 'success',
            self::SHIFTED, self::UNKNOWN => 'ghost',
            default => 'warning',
        };
    }
}
