<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalBillingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Rental;

use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Rental\{RentalChargeKind, RentalChargeStatus, RentalDepositStatus, RentalRateCardStatus};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Rental\{RentalCase, RentalCharge, RentalDeposit, RentalProfile, RentalRateCard};
use App\Services\Billing\{BillingModeResolver, DocumentTotalsCalculator};
use App\Services\Invoicing\{InvoiceGenerator, TaxResolver};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Kaufmännische Folge des Verleihs (MVP-262/266): Mietpositionen aus dem
 * Konditionen-Snapshot (D10), Freigabe mit Pflichtbegründung bei Schaden/
 * Verlust und Faktura-Übergabe. Bei externer Beleghoheit (Lexoffice/DATEV)
 * entsteht KEIN lokaler Beleg — die externe Belegnummer wird nachgetragen.
 * Die Kaution läuft als eigener Finanzvorgang, nie als Mietumsatz (D10).
 */
class RentalBillingService {
    public function __construct(
        private readonly BillingModeResolver $billingModes,
        private readonly InvoiceGenerator $invoices,
        private readonly TaxResolver $taxes,
        private readonly RentalPriceRuleService $rules,
    ) {}

    /**
     * Mietposition-Vorschläge aus dem eingefrorenen Konditionen-Snapshot:
     * Tagessätze über die Laufzeit, Pauschalen einmalig.
     *
     * @return list<array<string, mixed>>
     */
    public function suggestCharges(RentalCase $case): array {
        $groupCodes = $case->caseAssets->map(fn ($ca) => $ca->asset?->rentalProfile?->group_code)->filter()->unique()->values()->all();

        $snapshot = (array) ($case->terms_snapshot ?? []);

        return $this->chargesFor((array) ($snapshot['items'] ?? []), $case->starts_at, $case->actual_return_at ?? $case->ends_at, $groupCodes, array_values(array_filter((array) ($snapshot['rules'] ?? []), 'is_array')), isset($snapshot['utilization']) ? (int) $snapshot['utilization'] : null);
    }

    /**
     * Preisangabe fürs Portal (MVP-916): Summe der Vorschläge aus der
     * Standard-Preisliste des Geräts, netto; null ohne aktive Preisliste.
     */
    public function estimate(RentalProfile $profile, Carbon $from, Carbon $to): ?Money {
        $card = $profile->defaultRateCard;
        if (! $card instanceof RentalRateCard || $card->status !== RentalRateCardStatus::Active) {
            return null;
        }
        $snapshot = $card->toSnapshot();
        $charges = $this->chargesFor($snapshot['items'], $from, $to, array_filter([$profile->group_code]), $snapshot['rules'], $this->rules->utilization((int) $profile->organization_id, $profile->group_code, $from, $to));
        if ($charges === []) {
            return null;
        }
        $total = Money::zero(CurrencyCode::Euro);
        foreach ($charges as $charge) {
            $total = $total->plus(DocumentTotalsCalculator::lineNet($charge['quantity'], $charge['unit_price']));
        }

        return $total;
    }

    /**
     * Tagessätze über die Laufzeit, Pauschalen einmalig; gruppengebundene
     * Konditionen nur, wenn eine Position zur Gerätegruppe passt.
     *
     * @param  array<int, mixed>  $items
     * @param  array<int, string>  $groupCodes
     * @param  list<array<string, mixed>>  $rules  Mietpreisregeln aus dem Snapshot (MVP-950)
     * @return list<array<string, mixed>>
     */
    private function chargesFor(array $items, Carbon $from, Carbon $until, array $groupCodes, array $rules = [], ?int $utilization = null): array {
        if ($items === []) {
            return [];
        }

        $days = max(1, (int) ceil($from->diffInHours($until) / 24));

        $suggestions = [];
        foreach ($items as $item) {
            $kind = RentalChargeKind::tryFrom((string) ($item['kind'] ?? ''));
            if ($kind === null) {
                continue;
            }

            $group = $item['group_code'] ?? null;
            if ($group !== null && ! in_array($group, $groupCodes, true)) {
                continue;
            }

            $quantity = match ($kind) {
                RentalChargeKind::DailyRate => max($days, (int) ($item['min_duration_days'] ?? 0)),
                RentalChargeKind::HourlyRate => $from->diffInHours($until),
                // Zuschläge je betroffenem Tag (MVP-950), nicht mehr pauschal.
                RentalChargeKind::WeekendSurcharge => $this->rules->weekendDays($from, $until),
                RentalChargeKind::HolidaySurcharge => $this->rules->holidayDays($from, $until),
                default => 1,
            };

            if ($quantity <= 0 || in_array($kind, [RentalChargeKind::Damage, RentalChargeKind::Loss, RentalChargeKind::Discount], true)) {
                continue;
            }

            $suggestions[] = [
                'kind' => $kind->value,
                'label' => (string) ($item['label'] ?? $kind->label()),
                'quantity' => (float) $quantity,
                'unit' => (string) ($item['unit'] ?? 'day'),
                'unit_price' => (float) ($item['amount'] ?? 0),
            ];

            // Mietpreisregeln (MVP-950): Auf-/Abschlag je zutreffendem Tag auf den Tagessatz.
            if ($kind === RentalChargeKind::DailyRate) {
                foreach ($this->rules->adjustments($rules, $from, $until, $utilization) as $adjustment) {
                    $suggestions[] = [
                        'kind' => RentalChargeKind::Other->value,
                        'label' => (string) __('rental.rule.line', ['label' => $adjustment['label'], 'percent' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($adjustment['percent'], 0)]),
                        'quantity' => (float) $adjustment['days'],
                        'unit' => 'day',
                        'unit_price' => round((float) ($item['amount'] ?? 0) * $adjustment['percent'] / 100, 2),
                    ];
                }
            }
        }

        return $suggestions;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function addCharge(RentalCase $case, User $actor, array $attributes): RentalCharge {
        $kind = $attributes['kind'] instanceof RentalChargeKind
            ? $attributes['kind']
            : RentalChargeKind::from((string) $attributes['kind']);

        if ($kind->requiresReason() && trim((string) ($attributes['reason_text'] ?? '')) === '') {
            throw new \InvalidArgumentException((string) __('Schadens-, Verlust- und Minderungspositionen benötigen eine Pflichtbegründung.'));
        }

        $quantity = (float) ($attributes['quantity'] ?? 1);
        $unitPrice = (float) ($attributes['unit_price'] ?? 0);

        $charge = RentalCharge::query()->create(array_merge($attributes, [
            'organization_id' => $case->organization_id,
            'rental_case_id' => $case->id,
            'kind' => $kind->value,
            'status' => RentalChargeStatus::Draft->value,
            'amount' => DocumentTotalsCalculator::lineNet($quantity, $unitPrice)->withScale(2)->toFloat(),
            'created_by' => $actor->id,
        ]));

        $case->audit('rental.chargeAdded', ['kind' => $kind->value, 'amount' => (string) $charge->amount]);

        return $charge;
    }

    public function releaseCharge(RentalCharge $charge, User $actor): RentalCharge {
        if ($charge->status !== RentalChargeStatus::Draft) {
            throw new \RuntimeException((string) __('Nur Entwurfspositionen können freigegeben werden.'));
        }

        $charge->forceFill([
            'status' => RentalChargeStatus::Released->value,
            'released_by' => $actor->id,
            'released_at' => now(),
        ])->save();

        return $charge;
    }

    public function cancelCharge(RentalCharge $charge, User $actor, ?string $reason = null): RentalCharge {
        if ($charge->status->isSettled()) {
            throw new \RuntimeException((string) __('Abgerechnete Positionen können nicht storniert werden — Korrektur über Gutschrift/Storno am Beleg.'));
        }

        $charge->forceFill(['status' => RentalChargeStatus::Cancelled->value])->save();
        $charge->rentalCase?->audit('rental.chargeCancelled', ['charge_id' => $charge->id, 'reason' => $reason]);

        return $charge;
    }

    /**
     * Faktura-Übergabe (MVP-266): lokal entsteht ein Rechnungsentwurf mit
     * Positionen je Charge; bei externer Beleghoheit werden die Positionen
     * als übergeben markiert und die externe Belegnummer nachgetragen.
     */
    public function invoiceReleasedCharges(RentalCase $case, User $actor): ?Invoice {
        $customer = $case->customer()->firstOrFail();
        $mode = $this->billingModes->effectiveFor($customer);

        return DB::transaction(function () use ($case, $actor, $mode, $customer): ?Invoice {
            /** @var \Illuminate\Database\Eloquent\Collection<int, RentalCharge> $charges */
            $charges = $case->charges()->released()->lockForUpdate()->get();

            if ($charges->isEmpty()) {
                throw new \RuntimeException((string) __('Es gibt keine freigegebenen Positionen zur Übergabe.'));
            }

            if ($mode->isExternal()) {
                foreach ($charges as $charge) {
                    $charge->forceFill([
                        'status' => RentalChargeStatus::Transferred->value,
                        'invoiced_at' => now(),
                    ])->save();
                }

                $case->audit('rental.chargesTransferred', [
                    'mode' => $mode->value,
                    'count' => $charges->count(),
                ]);

                return null;
            }

            $organization = $customer->organization()->firstOrFail();
            $tax = $this->taxes->resolve($organization, $customer);

            $invoice = Invoice::create([
                'organization_id' => $case->organization_id,
                'customer_id' => $customer->id,
                'project_id' => $case->project_id,
                'number' => $this->invoices->nextNumber($case->organization_id),
                'status' => InvoiceStatus::Draft,
                'currency' => $customer->currency,
                'tax_rate' => $tax['rate'],
                'is_reverse_charge' => $tax['reverse_charge'],
                'notes' => $tax['note'] ?? null,
                'created_by' => $actor->id,
            ]);

            $position = 0;
            foreach ($charges as $charge) {
                $invoice->items()->create([
                    'rental_charge_id' => $charge->id,
                    'service_date' => ($case->actual_return_at ?? $case->ends_at)->toDateString(),
                    'description' => sprintf('%s — %s (%s)', $charge->label, $case->number, $charge->kind->label()),
                    'quantity' => (string) $charge->quantity,
                    'unit' => $charge->unit,
                    'unit_price' => (string) $charge->unit_price,
                    'position' => ++$position,
                ]);

                $charge->forceFill([
                    'status' => RentalChargeStatus::Invoiced->value,
                    'invoice_id' => $invoice->id,
                    'invoiced_at' => now(),
                ])->save();
            }

            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            $case->audit('rental.chargesInvoiced', ['invoice' => $invoice->number, 'count' => $charges->count()]);

            return $invoice;
        });
    }

    /**
     * Externe Belegnummer nachtragen — nur für extern übergebene Positionen.
     */
    public function recordExternalReference(RentalCharge $charge, string $reference): RentalCharge {
        if ($charge->status !== RentalChargeStatus::Transferred) {
            throw new \RuntimeException((string) __('Externe Belegnummern sind nur bei extern übergebenen Positionen zulässig.'));
        }

        $charge->forceFill(['external_reference' => $reference])->save();

        return $charge;
    }

    public function requestDeposit(RentalCase $case, User $actor, float $amount, ?string $note = null): RentalDeposit {
        if ($amount <= 0) {
            throw new \InvalidArgumentException((string) __('Die Kaution muss größer als 0 sein.'));
        }

        $deposit = RentalDeposit::query()->create([
            'organization_id' => $case->organization_id,
            'rental_case_id' => $case->id,
            'status' => RentalDepositStatus::Requested->value,
            'amount' => $amount,
            'recorded_by' => $actor->id,
            'note' => $note,
        ]);

        $case->audit('rental.depositRequested', ['amount' => (string) $deposit->amount]);

        return $deposit;
    }

    public function markDepositReceived(RentalDeposit $deposit, User $actor): RentalDeposit {
        if ($deposit->status !== RentalDepositStatus::Requested) {
            throw new \RuntimeException((string) __('Nur angeforderte Kautionen können als erhalten markiert werden.'));
        }

        $deposit->forceFill([
            'status' => RentalDepositStatus::Received->value,
            'received_at' => now(),
            'recorded_by' => $actor->id,
        ])->save();

        return $deposit;
    }

    /**
     * Kautionsabschluss: vollständige Erstattung, Teil- oder Voll-Einbehalt.
     * Einbehalt braucht eine Pflichtbegründung (Streit/Kulanz → Claims).
     */
    public function settleDeposit(RentalDeposit $deposit, User $actor, float $retainedAmount = 0.0, ?string $reason = null): RentalDeposit {
        if ($deposit->status !== RentalDepositStatus::Received) {
            throw new \RuntimeException((string) __('Nur erhaltene Kautionen können abgerechnet werden.'));
        }

        if ($retainedAmount > (float) $deposit->amount) {
            throw new \InvalidArgumentException((string) __('Der Einbehalt darf die Kaution nicht übersteigen.'));
        }

        if ($retainedAmount > 0 && trim((string) $reason) === '') {
            throw new \InvalidArgumentException((string) __('Ein Kautionseinbehalt benötigt eine Pflichtbegründung.'));
        }

        $status = match (true) {
            $retainedAmount <= 0.0 => RentalDepositStatus::Refunded,
            $retainedAmount < (float) $deposit->amount => RentalDepositStatus::PartiallyRetained,
            default => RentalDepositStatus::Retained,
        };

        $deposit->forceFill([
            'status' => $status->value,
            'retained_amount' => $retainedAmount > 0 ? $retainedAmount : null,
            'retained_reason' => $retainedAmount > 0 ? trim((string) $reason) : null,
            'refunded_at' => now(),
            'recorded_by' => $actor->id,
        ])->save();

        $deposit->rentalCase?->audit('rental.depositSettled', [
            'status' => $status->value,
            'retained' => (string) $retainedAmount,
        ]);

        return $deposit;
    }
}
