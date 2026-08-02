<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerAccountStatementService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Billing;

use App\Enums\Billing\AccountPaymentSource;
use App\Models\Billing\{CustomerAccountPayment, CustomerBillingAgreement, CustomerBillingStatement};
use App\Models\{TimeEntry, User};
use App\Support\Tz;
use Carbon\CarbonInterface;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kundenkonto-Monatsabrechnung (Feature 098, Konto-Modus): berechnet je Monat
 * Gesamt/Abgerechnet/Vormonat/Offen mit Übertrags-Kette (Excel-Analogie),
 * friert beim Abschluss einen Zeilen-Snapshot ein (Vorbild MonthClosure) und
 * markiert erfasste Einträge exported (Schutz vor Doppel-Fakturierung).
 *
 * Ketteninvariante: abgeschlossen wird nur der älteste offene, wiedereröffnet
 * nur der jüngste gesperrte Monat — sonst divergieren die carry_in-Werte.
 */
class CustomerAccountStatementService {
    public function ensure(CustomerBillingAgreement $agreement, int $year, int $month): CustomerBillingStatement {
        return CustomerBillingStatement::query()->firstOrCreate([
            'customer_billing_agreement_id' => $agreement->id,
            'year' => $year,
            'month' => $month,
        ], [
            'organization_id' => $agreement->organization_id,
        ]);
    }

    /**
     * Alle offenen Monate chronologisch neu berechnen (Übertrags-Kette).
     * Liefert Warnungen zu Nachträgen: billable Einträge, deren Datum in einem
     * gesperrten Monat liegt, die dort aber nicht im Snapshot stehen.
     *
     * @return array{stray_entries: list<array{id: int, date: string, minutes: int}>}
     */
    public function recalculateOpen(CustomerBillingAgreement $agreement): array {
        $statements = $agreement->statements()->orderBy('year')->orderBy('month')->get();
        $lastLocked = $statements->where('locked', true)->last();

        [$start, $prevBalance] = $this->chainAnchor($agreement, $lastLocked);
        $end = $this->chainEnd($agreement);

        if ($start !== null && $start->lessThanOrEqualTo($end)) {
            $cursor = $start->copy();
            while ($cursor->lessThanOrEqualTo($end)) {
                $statement = $this->ensure($agreement, $cursor->year, $cursor->month);
                if (! $statement->locked) {
                    $this->computePeriod($agreement, $statement, $prevBalance);
                }
                $prevBalance = $this->asMoney($statement->balance, $agreement->currency);
                $cursor->addMonthNoOverflow();
            }
        }

        return ['stray_entries' => $this->strayEntries($agreement, $statements->where('locked', true))];
    }

    /**
     * Satzänderung auf offene Monate anwenden. Erfasst zwei Gruppen:
     * Einträge MIT Konditions-Marker (Satz wurde geändert) und Einträge ganz
     * OHNE Satz — Bestandszeiten, die vor Anlage der Kondition erfasst wurden
     * und sonst dauerhaft mit 0,00 € im Saldo stünden. Manuelle Overrides
     * (hourly_rate gesetzt, FK=NULL) bleiben unangetastet.
     *
     * @return array{stray_entries: list<array{id: int, date: string, minutes: int}>}
     */
    public function reapplyRates(CustomerBillingAgreement $agreement): array {
        $openStart = $this->firstOpenPeriodStart($agreement);

        $query = $this->entriesQuery($agreement)
            ->where(fn ($q) => $q->whereNotNull('customer_billing_rate_id')->orWhereNull('hourly_rate'));
        if ($openStart !== null) {
            $query->whereDate('date', '>=', $openStart->toDateString());
        }

        foreach ($query->get() as $entry) {
            $entry->hourly_rate = null;
            $entry->customer_billing_rate_id = null;
            // Direkt rechnen statt auf den saving-Hook zu bauen: bei einem
            // Eintrag, dessen hourly_rate schon NULL war, wird kein Feld dirty
            // — der Hook liefe nicht und der Satz bliebe bei 0,00 €.
            $entry->applyRateSnapshot();
            $entry->save();
        }

        return $this->recalculateOpen($agreement);
    }

    public function close(CustomerBillingStatement $statement, User $actor): CustomerBillingStatement {
        $agreement = $statement->agreement()->firstOrFail();

        if ($statement->locked) {
            throw ValidationException::withMessages(['statement' => __('customer-billing.error_month_already_closed')]);
        }

        $olderOpen = $agreement->statements()
            ->where('locked', false)
            ->whereRaw('(year * 100 + month) < ?', [$statement->year * 100 + $statement->month])
            ->exists();
        if ($olderOpen) {
            throw ValidationException::withMessages(['statement' => __('customer-billing.error_older_open_month')]);
        }

        return DB::transaction(function () use ($agreement, $statement, $actor): CustomerBillingStatement {
            $this->recalculateOpen($agreement);
            $statement->refresh();

            $data = $this->buildMonthData($agreement, $statement);
            $entryIds = array_map(static fn (array $row): int => $row['id'], $data['rows']);

            $statement->fill([
                'totals' => [
                    'entry_ids' => $entryIds,
                    'rows' => $data['rows'],
                    'payments' => $data['payments'],
                    'by_category' => $data['by_category'],
                ],
                'locked' => true,
                'locked_at' => now(),
                'locked_by_user_id' => $actor->id,
            ])->save();

            if ($entryIds !== []) {
                TimeEntry::query()->whereIn('id', $entryIds)->update(['exported' => true]);
            }

            // Folgemonat anlegen, damit der Übertrag sofort sichtbar ist.
            $next = $statement->periodStart()->addMonthNoOverflow();
            $this->ensure($agreement, $next->year, $next->month);
            $this->recalculateOpen($agreement);

            return $statement->refresh();
        });
    }

    public function reopen(CustomerBillingStatement $statement, User $actor): CustomerBillingStatement {
        $agreement = $statement->agreement()->firstOrFail();

        if (! $statement->locked) {
            throw ValidationException::withMessages(['statement' => __('customer-billing.error_month_not_closed')]);
        }

        $newerLocked = $agreement->statements()
            ->where('locked', true)
            ->whereRaw('(year * 100 + month) > ?', [$statement->year * 100 + $statement->month])
            ->exists();
        if ($newerLocked) {
            throw ValidationException::withMessages(['statement' => __('customer-billing.error_newer_locked_month')]);
        }

        return DB::transaction(function () use ($agreement, $statement): CustomerBillingStatement {
            $entryIds = (array) ($statement->totals['entry_ids'] ?? []);
            if ($entryIds !== []) {
                TimeEntry::query()->whereIn('id', $entryIds)->update(['exported' => false]);
            }

            $statement->fill([
                'locked' => false,
                'locked_at' => null,
                'locked_by_user_id' => null,
                'totals' => null,
            ])->save();

            $this->recalculateOpen($agreement);

            return $statement->refresh();
        });
    }

    /**
     * @param array{paid_on: string|CarbonInterface, amount: float|string, source?: AccountPaymentSource|string, source_reference?: string|null, bank_transaction_id?: int|null, payment_allocation_id?: int|null, note?: string|null} $data
     */
    public function bookPayment(CustomerBillingAgreement $agreement, array $data, ?User $actor = null): CustomerAccountPayment {
        $paidOn = Carbon::parse($data['paid_on'], Tz::current());

        $locked = $agreement->statements()
            ->where('year', $paidOn->year)
            ->where('month', $paidOn->month)
            ->where('locked', true)
            ->exists();
        if ($locked) {
            throw ValidationException::withMessages(['paid_on' => __('customer-billing.error_target_month_locked')]);
        }

        $payment = CustomerAccountPayment::create([
            'organization_id' => $agreement->organization_id,
            'customer_billing_agreement_id' => $agreement->id,
            'paid_on' => $paidOn->toDateString(),
            'amount' => $data['amount'],
            'currency' => $agreement->currency->value,
            'source' => $data['source'] ?? AccountPaymentSource::Manual,
            'source_reference' => $data['source_reference'] ?? null,
            'bank_transaction_id' => $data['bank_transaction_id'] ?? null,
            'payment_allocation_id' => $data['payment_allocation_id'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by_user_id' => $actor?->id,
        ]);

        $this->recalculateOpen($agreement);

        return $payment;
    }

    /**
     * Idempotente Verbuchung einer Lexoffice-Zahlung (Retainer-Modus,
     * Feature 098) in den Leistungssaldo — Dedup über die Voucher-UUID
     * (source_reference). Anders als bookPayment wirft ein gesperrter
     * Zielmonat NICHT, sondern der Betrag wandert in den ersten offenen Monat
     * (der Lexoffice-Sync darf nie abbrechen). Betrag ≤ 0 ⇒ keine Buchung.
     */
    public function bookLexofficePayment(
        CustomerBillingAgreement $agreement,
        string $sourceReference,
        Money $amount,
        CarbonInterface $paidOn,
        ?string $note = null,
    ): ?CustomerAccountPayment {
        if (! $amount->isPositive()) {
            return null;
        }

        $target = Carbon::parse($paidOn)->setTimezone(Tz::current());
        $effective = $this->firstUnlockedFrom($agreement, $target);

        $values = [
            'organization_id' => $agreement->organization_id,
            'paid_on' => $effective->toDateString(),
            'amount' => $this->asMoney($amount, $agreement->currency),
            'currency' => $agreement->currency->value,
            'note' => $note,
        ];

        // uq_cap_source_ref kennt kein deleted_at: eine stornierte Zeile
        // (revoke/unlink) blockiert den Insert weiterhin. Darum stornierte
        // Zeilen mitsuchen und wiederbeleben statt neu anzulegen.
        $payment = CustomerAccountPayment::withTrashed()
            ->where('customer_billing_agreement_id', $agreement->id)
            ->where('source', AccountPaymentSource::Lexoffice)
            ->where('source_reference', $sourceReference)
            ->first();

        if ($payment === null) {
            $payment = CustomerAccountPayment::create($values + [
                'customer_billing_agreement_id' => $agreement->id,
                'source' => AccountPaymentSource::Lexoffice,
                'source_reference' => $sourceReference,
            ]);
        } else {
            $payment->fill($values);
            $payment->trashed() ? $payment->restore() : $payment->save();
        }

        $this->recalculateOpen($agreement);

        return $payment;
    }

    /** Storniert eine zuvor gebuchte Lexoffice-Zahlung (Void/Gutschrift). */
    public function revokeLexofficePayment(CustomerBillingAgreement $agreement, string $sourceReference): void {
        $payment = $agreement->payments()
            ->where('source', AccountPaymentSource::Lexoffice->value)
            ->where('source_reference', $sourceReference)
            ->first();

        if ($payment !== null) {
            $payment->delete();
            $this->recalculateOpen($agreement);
        }
    }

    /**
     * Zielmonat des Zahldatums, oder — falls dieser gesperrt ist — der erste
     * darauf folgende offene Monat (angelegt falls nötig).
     */
    private function firstUnlockedFrom(CustomerBillingAgreement $agreement, CarbonInterface $from): Carbon {
        $cursor = Carbon::parse($from)->startOfMonth();
        for ($i = 0; $i < 240; $i++) {
            $locked = $agreement->statements()
                ->where('year', $cursor->year)
                ->where('month', $cursor->month)
                ->where('locked', true)
                ->exists();
            if (! $locked) {
                return $cursor;
            }
            $cursor = $cursor->addMonthNoOverflow();
        }

        return $cursor;
    }

    /**
     * Anzeige-/PDF-Daten eines Monats: Tageszeilen + Abrechnungsblock.
     * Gesperrt ⇒ aus dem eingefrorenen Snapshot, offen ⇒ live („vorläufig").
     *
     * @return array{statement: CustomerBillingStatement, rows: array<int, array<string, mixed>>, payments: array<int, array<string, mixed>>, by_category: array<int, array<string, mixed>>, locked: bool}
     */
    public function monthData(CustomerBillingAgreement $agreement, int $year, int $month): array {
        $this->recalculateOpen($agreement);
        $statement = $this->ensure($agreement, $year, $month)->refresh();

        if ($statement->locked && is_array($statement->totals)) {
            return [
                'statement' => $statement,
                'rows' => array_values((array) ($statement->totals['rows'] ?? [])),
                'payments' => array_values((array) ($statement->totals['payments'] ?? [])),
                'by_category' => array_values((array) ($statement->totals['by_category'] ?? [])),
                'locked' => true,
            ];
        }

        $data = $this->buildMonthData($agreement, $statement);

        return [
            'statement' => $statement,
            'rows' => $data['rows'],
            'payments' => $data['payments'],
            'by_category' => $data['by_category'],
            'locked' => false,
        ];
    }

    // ------------------------------------------------------------------
    // interne Bausteine
    // ------------------------------------------------------------------

    /** @return array{0: Carbon|null, 1: Money} Startmonat + Anfangs-Übertrag der offenen Kette. */
    private function chainAnchor(CustomerBillingAgreement $agreement, ?CustomerBillingStatement $lastLocked): array {
        if ($lastLocked !== null) {
            return [$lastLocked->periodStart()->addMonthNoOverflow(), $this->asMoney($lastLocked->balance, $agreement->currency)];
        }

        $openingBalance = $this->asMoney($agreement->opening_balance, $agreement->currency);
        if ($agreement->opening_balance_date !== null) {
            return [
                Carbon::parse($agreement->opening_balance_date, Tz::current())->startOfMonth()->addMonthNoOverflow(),
                $openingBalance,
            ];
        }

        $firstDate = $this->earliestActivityDate($agreement);
        if ($firstDate === null) {
            // Keine (aktiven) Einträge/Zahlungen mehr — bestehende Statements
            // trotzdem neu rechnen (z. B. nach unmatch der letzten Zahlung).
            $firstDate = $agreement->statements()->orderBy('year')->orderBy('month')->first()?->periodStart();
        }

        return [$firstDate?->copy()->startOfMonth(), $openingBalance];
    }

    private function chainEnd(CustomerBillingAgreement $agreement): Carbon {
        // Enthält immer den aktuellen Monat, ist also nie leer.
        $candidates = array_filter([
            $this->latestActivityDate($agreement)?->copy()->startOfMonth(),
            Carbon::now(Tz::current())->startOfMonth(),
        ]);

        return max($candidates);
    }

    private function firstOpenPeriodStart(CustomerBillingAgreement $agreement): ?Carbon {
        $lastLocked = $agreement->statements()
            ->where('locked', true)
            ->orderByDesc('year')->orderByDesc('month')
            ->first();

        return $lastLocked?->periodStart()->addMonthNoOverflow();
    }

    private function computePeriod(CustomerBillingAgreement $agreement, CustomerBillingStatement $statement, Money $carryIn): void {
        $start = $statement->periodStart();
        $end = $start->copy()->endOfMonth();

        $entries = $this->entriesQuery($agreement)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get();

        $payments = $this->paymentsFor($agreement, $start, $end);

        $currency = $agreement->currency;
        $gross = Money::sum($entries->map(fn (TimeEntry $e): Money => $this->asMoney($e->rate, $currency)), $currency);
        $paid = Money::sum($payments->map(fn (CustomerAccountPayment $p): Money => $this->asMoney($p->amount, $currency)), $currency);

        $statement->fill([
            'total_minutes' => (int) $entries->sum('minutes'),
            'gross_value' => $gross,
            'payments_total' => $paid,
            'carry_in' => $carryIn,
            'balance' => $carryIn->plus($gross)->minus($paid),
            'computed_at' => now(),
        ])->save();
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, payments: array<int, array<string, mixed>>, by_category: array<int, array<string, mixed>>}
     */
    private function buildMonthData(CustomerBillingAgreement $agreement, CustomerBillingStatement $statement): array {
        $start = $statement->periodStart();
        $end = $start->copy()->endOfMonth();
        $tz = Tz::current();
        $currency = $agreement->currency;

        $entries = $this->entriesQuery($agreement)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->with('activityCategory')
            ->orderBy('started_at')
            ->get();

        $rows = $entries->map(function (TimeEntry $entry) use ($tz, $currency): array {
            $localStart = $entry->started_at?->copy()->setTimezone($tz);
            $localEnd = $entry->ended_at?->copy()->setTimezone($tz);
            $category = $entry->getRelationValue('activityCategory');

            return [
                'id' => $entry->id,
                'date' => $entry->date?->toDateString(),
                'weekday' => $entry->date?->translatedFormat('l'),
                'category' => $category instanceof \App\Models\ActivityCategory
                    ? $category->label
                    : TimeEntry::activityLabel($entry->activity_type),
                'start' => $localStart?->format('H:i'),
                'end' => $localEnd?->format('H:i'),
                'minutes' => (int) $entry->minutes,
                'hourly_rate' => $entry->hourly_rate?->toFloat(),
                // Snapshot-/JSON-Grenze: Beträge als float (Vorbild totals-Spalte).
                'amount' => $this->asMoney($entry->rate, $currency)->toFloat(),
            ];
        })->values()->all();

        $payments = $this->paymentsFor($agreement, $start, $end)->map(fn (CustomerAccountPayment $p): array => [
            'id' => $p->id,
            'paid_on' => $p->paid_on->toDateString(),
            'amount' => $p->amount?->toFloat() ?? 0.0,
            'source' => $p->source->value,
            'note' => $p->note,
        ])->values()->all();

        $byCategory = collect($rows)
            ->groupBy('category')
            ->map(fn (Collection $group, string $label): array => [
                'label' => $label,
                'minutes' => (int) $group->sum('minutes'),
                'amount' => Money::sum($group->map(fn (array $row): Money => Money::ofFloat((float) $row['amount'], $currency)), $currency)->toFloat(),
            ])->values()->all();

        return ['rows' => $rows, 'payments' => $payments, 'by_category' => $byCategory];
    }

    /**
     * Hebt ein Geld-Attribut währungsfest auf die Agreement-Währung (Betrag
     * unverändert): Statements/Zeiteinträge tragen keine eigene Währungsspalte,
     * ihr Cast fällt auf die Standardwährung zurück — ohne Normierung würde
     * die Money-Arithmetik bei Nicht-EUR-Agreements Währungsmix melden.
     */
    private function asMoney(?Money $value, CurrencyCode $currency): Money {
        if ($value === null) {
            return Money::zero($currency);
        }

        return $value->getCurrency() === $currency ? $value : Money::of($value->getAmount(), $currency);
    }

    /** @return Collection<int, CustomerAccountPayment> */
    private function paymentsFor(CustomerBillingAgreement $agreement, CarbonInterface $start, CarbonInterface $end): Collection {
        return $agreement->payments()
            ->whereDate('paid_on', '>=', $start->toDateString())
            ->whereDate('paid_on', '<=', $end->toDateString())
            ->orderBy('paid_on')
            ->get();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<TimeEntry> */
    private function entriesQuery(CustomerBillingAgreement $agreement) {
        return TimeEntry::query()
            ->whereHas('project', fn ($q) => $q->where('customer_id', $agreement->customer_id))
            ->where('billable', true);
    }

    private function earliestActivityDate(CustomerBillingAgreement $agreement): ?Carbon {
        $entryDate = $this->entriesQuery($agreement)->min('date');
        $paymentDate = $agreement->payments()->min('paid_on');
        $dates = array_filter([$entryDate, $paymentDate]);

        return $dates === [] ? null : Carbon::parse(min($dates), Tz::current());
    }

    private function latestActivityDate(CustomerBillingAgreement $agreement): ?Carbon {
        $entryDate = $this->entriesQuery($agreement)->max('date');
        $paymentDate = $agreement->payments()->max('paid_on');
        $dates = array_filter([$entryDate, $paymentDate]);

        return $dates === [] ? null : Carbon::parse(max($dates), Tz::current());
    }

    /**
     * @param  Collection<int, CustomerBillingStatement>  $lockedStatements
     * @return list<array{id: int, date: string, minutes: int}>
     */
    private function strayEntries(CustomerBillingAgreement $agreement, Collection $lockedStatements): array {
        $stray = [];
        foreach ($lockedStatements as $statement) {
            $snapshotIds = (array) ($statement->totals['entry_ids'] ?? []);
            $start = $statement->periodStart();
            $end = $start->copy()->endOfMonth();

            $entries = $this->entriesQuery($agreement)
                ->whereDate('date', '>=', $start->toDateString())
                ->whereDate('date', '<=', $end->toDateString())
                ->whereNotIn('id', $snapshotIds === [] ? [0] : $snapshotIds)
                ->get(['id', 'date', 'minutes']);

            foreach ($entries as $entry) {
                $stray[] = [
                    'id' => $entry->id,
                    'date' => (string) $entry->date?->toDateString(),
                    'minutes' => (int) $entry->minutes,
                ];
            }
        }

        return $stray;
    }
}
