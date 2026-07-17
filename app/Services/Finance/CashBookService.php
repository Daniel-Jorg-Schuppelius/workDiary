<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CashBookService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Finance;

use App\Models\{CashDailyClosing, CashEntry, CashRegister, Invoice};
use Carbon\{Carbon, CarbonInterface};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Kassenbuch (MVP-414) — EINZIGE Schreibstelle für Kasseneinträge (Muster
 * AgileEvent::record): fortlaufende lückenlose seq_no je Kasse, append-only
 * Hash-Kette (HashChained), Korrektur nur als Storno-Gegenbuchung, keine
 * Buchung in abgeschlossene Tage. Abgrenzung: kein POS/§ 146a AO — das
 * Kassenbuch dokumentiert Bargeschäfte, es erzeugt keine Kassenbelege
 * mit TSE-Signatur.
 */
class CashBookService {
    /**
     * Bareinnahme/-ausgabe erfassen.
     *
     * @param  array{booked_on: string, direction: string, amount: float|string, purpose: string,
     *               tax_rate?: float|string|null, counterparty?: string|null, invoice_id?: int|null,
     *               created_by?: int|null}  $data
     */
    public function record(CashRegister $register, array $data): CashEntry {
        $bookedOn = Carbon::parse((string) $data['booked_on']);
        $this->assertNotClosed($register, $bookedOn);

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Kassenbetrag muss positiv sein (Richtung über direction).');
        }

        return DB::transaction(function () use ($register, $data, $bookedOn, $amount): CashEntry {
            $entry = CashEntry::create([
                'organization_id' => $register->organization_id,
                'cash_register_id' => $register->id,
                'seq_no' => $this->nextSeqNo($register),
                'booked_on' => $bookedOn->toDateString(),
                'direction' => $data['direction'],
                'amount' => (string) $amount,
                'tax_rate' => $data['tax_rate'] ?? null,
                'purpose' => (string) $data['purpose'],
                'counterparty' => $data['counterparty'] ?? null,
                'invoice_id' => $data['invoice_id'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            if ($entry->invoice_id !== null && $entry->direction === CashEntry::DIRECTION_IN) {
                $this->applyInvoicePayment($entry);
            }

            return $entry;
        });
    }

    /**
     * Storno-Gegenbuchung (GoBD: nie löschen/ändern): umgekehrte Richtung,
     * gleicher Betrag, Pflicht-Begründung; das Original bleibt unangetastet.
     */
    public function reverse(CashEntry $original, string $reason, ?int $userId = null, ?CarbonInterface $bookedOn = null): CashEntry {
        if ($original->reversal_of_id !== null) {
            throw new InvalidArgumentException('Eine Storno-Buchung kann nicht erneut storniert werden.');
        }
        $alreadyReversed = CashEntry::query()
            ->where('reversal_of_id', $original->id)
            ->exists();
        if ($alreadyReversed) {
            throw new InvalidArgumentException('Dieser Eintrag wurde bereits storniert.');
        }

        /** @var CashRegister $register */
        $register = $original->register()->firstOrFail();
        $bookedOn = Carbon::parse(($bookedOn ?? Carbon::today())->toDateString());
        $this->assertNotClosed($register, $bookedOn);

        return DB::transaction(fn(): CashEntry => CashEntry::create([
            'organization_id' => $original->organization_id,
            'cash_register_id' => $original->cash_register_id,
            'seq_no' => $this->nextSeqNo($register),
            'booked_on' => $bookedOn->toDateString(),
            'direction' => $original->direction === CashEntry::DIRECTION_IN ? CashEntry::DIRECTION_OUT : CashEntry::DIRECTION_IN,
            'amount' => (string) $original->amount,
            'tax_rate' => $original->tax_rate,
            'purpose' => (string) __('Storno zu Beleg #:seq: :reason', ['seq' => $original->seq_no, 'reason' => $reason]),
            'counterparty' => $original->counterparty,
            'reversal_of_id' => $original->id,
            'created_by' => $userId,
        ]));
    }

    /**
     * Tagesabschluss mit Kassensturz: friert alle Buchungen bis einschließlich
     * `$closingDate` ein und protokolliert Soll/Ist/Differenz.
     */
    public function closeDay(CashRegister $register, CarbonInterface $closingDate, float $countedBalance, ?string $note = null, ?int $userId = null): CashDailyClosing {
        $closingDate = Carbon::parse($closingDate->toDateString());
        $last = $register->lastClosingDate();
        if ($last !== null && $closingDate->lessThanOrEqualTo($last)) {
            throw new InvalidArgumentException('Für dieses Datum existiert bereits ein Tagesabschluss.');
        }

        $expected = $this->balanceAsOf($register, $closingDate);

        return CashDailyClosing::create([
            'organization_id' => $register->organization_id,
            'cash_register_id' => $register->id,
            'closing_date' => $closingDate->toDateString(),
            'expected_balance' => (string) $expected,
            'counted_balance' => (string) round($countedBalance, 2),
            'difference' => (string) round($countedBalance - $expected, 2),
            'note' => $note,
            'closed_by' => $userId,
        ]);
    }

    /** Laufender Kassensaldo (Anfangsbestand + Einnahmen − Ausgaben). */
    public function balance(CashRegister $register): float {
        return $this->balanceAsOf($register, null);
    }

    public function balanceAsOf(CashRegister $register, ?CarbonInterface $date): float {
        $query = CashEntry::query()->where('cash_register_id', $register->id);
        if ($date !== null) {
            $query->whereDate('booked_on', '<=', $date->toDateString());
        }

        $in = (float) (clone $query)->where('direction', CashEntry::DIRECTION_IN)->sum('amount');
        $out = (float) (clone $query)->where('direction', CashEntry::DIRECTION_OUT)->sum('amount');

        return round((float) $register->opening_balance + $in - $out, 2);
    }

    /** Buchungen in abgeschlossene Tage sind unzulässig (GoBD-Festschreibung). */
    private function assertNotClosed(CashRegister $register, CarbonInterface $bookedOn): void {
        $last = $register->lastClosingDate();
        if ($last !== null && Carbon::parse($bookedOn->toDateString())->lessThanOrEqualTo($last)) {
            throw new InvalidArgumentException(
                'Der Tag ist bereits abgeschlossen (letzter Abschluss: ' . $last->format('d.m.Y') . ') — Korrektur nur als Storno mit neuem Datum.',
            );
        }
    }

    /** Lückenlose fortlaufende Belegnummer je Kasse (unter Registersperre). */
    private function nextSeqNo(CashRegister $register): int {
        // Registerzeile sperren, damit parallele Buchungen keine seq_no doppeln.
        CashRegister::query()->withoutGlobalScopes()->whereKey($register->id)->lockForUpdate()->first();

        return (int) CashEntry::query()
            ->withoutGlobalScopes()
            ->where('cash_register_id', $register->id)
            ->max('seq_no') + 1;
    }

    /**
     * Barzahlung einer Rechnung über den bestehenden Zahlungsstatus-Pfad
     * (status/paid_on sind nach Ausstellung bewusst änderbar — Lifecycle).
     */
    private function applyInvoicePayment(CashEntry $entry): void {
        /** @var Invoice|null $invoice */
        $invoice = Invoice::query()->find($entry->invoice_id);
        if ($invoice === null || $invoice->status === Invoice::STATUS_PAID) {
            return;
        }

        $paidCash = (float) CashEntry::query()
            ->where('invoice_id', $invoice->id)
            ->where('direction', CashEntry::DIRECTION_IN)
            ->sum('amount');

        if ($paidCash + 0.005 >= (float) $invoice->total) {
            $invoice->status = Invoice::STATUS_PAID;
            $invoice->paid_on = \Illuminate\Support\Carbon::parse($entry->booked_on->toDateString());
            $invoice->save();
        } elseif ($invoice->status === Invoice::STATUS_ISSUED && $paidCash > 0) {
            $invoice->status = Invoice::STATUS_PARTIALLY_PAID;
            $invoice->save();
        }
    }
}
