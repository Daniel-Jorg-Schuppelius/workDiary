<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodClosingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\{AccountingEntryStatus, AccountingPeriodStatus};
use App\Models\Accounting\{AccountingEntry, AccountingFiscalYear, AccountingPeriod};
use App\Models\{Organization, User};
use App\Services\Accounting\Preflight\{AccountingPreflightCheck, AccountingPreflightReport};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Periodenabschluss (Feature 125, MVP-677) — EINZIGE Schreibstelle für den
 * Status von Perioden und Geschäftsjahren.
 *
 * Zwei Stufen mit unterschiedlicher Härte:
 *
 *  - `soft_closed`: inhaltlich fertig, eine berechtigte Korrektur ist noch
 *    möglich. Der Zustand ist ein Signal an die Organisation, keine Sperre
 *    gegen sie.
 *  - `closed`: gesperrt. Neue Buchungen mit Buchungsdatum in der Periode
 *    werden vom {@see JournalService} abgewiesen; eine Wiedereröffnung
 *    braucht ein eigenes Recht, eine Begründung und landet in der Hash-Kette.
 *
 * Der Abschluss prüft vorher, was danach nicht mehr zu reparieren wäre:
 * offene Entwürfe und unausgeglichene Buchungen.
 */
class PeriodClosingService {
    public function __construct(private readonly AccountingEventRecorder $events) {}

    /**
     * Prüfstand vor dem Abschluss. Blockierend ist, was nach dem Schließen
     * nur noch über eine Wiedereröffnung zu beheben wäre.
     */
    public function preflight(AccountingPeriod $period): AccountingPreflightReport {
        $checks = [];

        $drafts = AccountingEntry::query()
            ->where('accounting_period_id', $period->id)
            ->whereIn('status', [AccountingEntryStatus::Draft->value, AccountingEntryStatus::Ready->value])
            ->count();

        $checks[] = $drafts === 0
            ? AccountingPreflightCheck::passed('drafts', (string) __('accounting.closing.check.no_drafts'))
            : AccountingPreflightCheck::blocked('drafts', (string) __('accounting.closing.check.drafts', ['count' => $drafts]));

        $unbalanced = 0;
        foreach (AccountingEntry::query()->where('accounting_period_id', $period->id)->with('lines')->get() as $entry) {
            if (! $entry->isBalanced()) {
                $unbalanced++;
            }
        }

        $checks[] = $unbalanced === 0
            ? AccountingPreflightCheck::passed('balanced', (string) __('accounting.closing.check.balanced'))
            : AccountingPreflightCheck::blocked('balanced', (string) __('accounting.closing.check.unbalanced', ['count' => $unbalanced]));

        // Eine Lücke davor ist kein Fehler, aber ein Hinweis wert: Perioden
        // schließt man üblicherweise der Reihe nach.
        $earlierOpen = AccountingPeriod::query()
            ->where('organization_id', $period->organization_id)
            ->where('status', AccountingPeriodStatus::Open->value)
            ->whereDate('ends_on', '<', $period->starts_on->toDateString())
            ->count();

        $checks[] = $earlierOpen === 0
            ? AccountingPreflightCheck::passed('sequence', (string) __('accounting.closing.check.sequence_ok'))
            : AccountingPreflightCheck::warning('sequence', (string) __('accounting.closing.check.earlier_open', ['count' => $earlierOpen]));

        return new AccountingPreflightReport($checks);
    }

    /** Vorläufiger Abschluss — reversibel ohne Wiedereröffnungsnachweis. */
    public function softClose(AccountingPeriod $period, User $actor): AccountingPeriod {
        $this->assertStatus($period, [AccountingPeriodStatus::Open]);

        $period->update([
            'status' => AccountingPeriodStatus::SoftClosed,
            'soft_closed_at' => now(),
        ]);

        $this->record($period, 'accounting.period_soft_closed', $actor, []);

        return $period->refresh();
    }

    /** Endgültiger Abschluss — danach nimmt die Periode keine Buchung mehr an. */
    public function close(AccountingPeriod $period, User $actor): AccountingPeriod {
        $this->assertStatus($period, [AccountingPeriodStatus::Open, AccountingPeriodStatus::SoftClosed]);

        $report = $this->preflight($period);
        if (! $report->isReady()) {
            throw ValidationException::withMessages([
                'preflight' => array_map(fn ($check): string => $check->message, $report->blockers()),
            ]);
        }

        return DB::transaction(function () use ($period, $actor, $report): AccountingPeriod {
            $period->update([
                'status' => AccountingPeriodStatus::Closed,
                'closed_at' => now(),
                'closed_by' => $actor->id,
            ]);

            $this->record($period, 'accounting.period_closed', $actor, [
                'preflight' => $report->toArray(),
                'starts_on' => $period->starts_on->toDateString(),
                'ends_on' => $period->ends_on->toDateString(),
            ]);

            return $period->refresh();
        });
    }

    /**
     * Wiedereröffnung — eigenes Recht, Pflichtbegründung, Nachweis in der
     * Kette. Ohne diese drei wäre der Abschluss nur eine Sichtbarkeit.
     */
    public function reopen(AccountingPeriod $period, User $actor, string $reason): AccountingPeriod {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reopen_reason' => (string) __('accounting.closing.error.reason_required'),
            ]);
        }

        if ($period->status === AccountingPeriodStatus::Open) {
            throw ValidationException::withMessages([
                'status' => (string) __('accounting.closing.error.already_open'),
            ]);
        }

        return DB::transaction(function () use ($period, $actor, $reason): AccountingPeriod {
            $period->update([
                'status' => AccountingPeriodStatus::Open,
                'reopened_at' => now(),
                'reopened_by' => $actor->id,
                'reopen_reason' => $reason,
                'closed_at' => null,
                'closed_by' => null,
                'soft_closed_at' => null,
            ]);

            // Ein geschlossenes Jahr über einer offenen Periode wäre ein
            // Widerspruch — das Jahr geht mit auf.
            $year = $period->fiscalYear;
            if ($year instanceof AccountingFiscalYear && $year->status !== AccountingPeriodStatus::Open) {
                $year->update(['status' => AccountingPeriodStatus::Open, 'closed_at' => null, 'closed_by' => null]);
            }

            $this->record($period, 'accounting.period_reopened', $actor, ['reason' => $reason]);

            return $period->refresh();
        });
    }

    /** Jahresabschluss: nur wenn alle Perioden geschlossen sind. */
    public function closeFiscalYear(AccountingFiscalYear $year, User $actor): AccountingFiscalYear {
        $open = $year->periods()->where('status', '!=', AccountingPeriodStatus::Closed->value)->count();
        if ($open > 0) {
            throw ValidationException::withMessages([
                'periods' => (string) __('accounting.closing.error.periods_open', ['count' => $open]),
            ]);
        }

        $year->update([
            'status' => AccountingPeriodStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $actor->id,
        ]);

        $organization = $year->organization;
        if ($organization instanceof Organization) {
            $this->events->record($organization, 'accounting.fiscal_year_closed', [
                'label' => $year->label,
                'starts_on' => $year->starts_on->toDateString(),
                'ends_on' => $year->ends_on->toDateString(),
            ], null, $actor);
        }

        return $year->refresh();
    }

    /**
     * Perioden eines Zeitraums (Abschluss-Arbeitsliste).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AccountingPeriod>
     */
    public function periodsFor(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): \Illuminate\Database\Eloquent\Collection {
        return AccountingPeriod::query()
            ->where('organization_id', $organization->id)
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->with('fiscalYear')
            ->orderBy('starts_on')
            ->get();
    }

    /** @param list<AccountingPeriodStatus> $allowed */
    private function assertStatus(AccountingPeriod $period, array $allowed): void {
        if (! in_array($period->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => (string) __('accounting.closing.error.wrong_status', ['status' => $period->status->label()]),
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function record(AccountingPeriod $period, string $event, User $actor, array $payload): void {
        $organization = $period->organization;
        if (! $organization instanceof Organization) {
            return;
        }

        $this->events->record($organization, $event, $payload + [
            'period_id' => $period->id,
            'period' => $period->starts_on->toDateString() . '/' . $period->ends_on->toDateString(),
        ], null, $actor);
    }
}
