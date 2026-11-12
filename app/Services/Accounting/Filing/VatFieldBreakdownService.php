<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VatFieldBreakdownService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Filing;

use App\Enums\Finance\{AccountingEntryStatus, TaxCodeDirection};
use App\Models\Accounting\{AccountingEntryLine, AccountingTaxCode};
use App\Models\Platform\Organization;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Aufteilung nach den Kennziffern der Voranmeldung (Feature 125, MVP-688).
 *
 * Die Zuordnung kommt vom Steuerkennzeichen der Buchungszeile. Es steht
 * entweder an der Nettozeile (Handbuchung: Zeilenbetrag = Grundlage,
 * `tax_amount` = Steuer) oder an der Steuerzeile (Inbox-Vorschläge: Zeile =
 * Steuer, Grundlage aus dem Satz) — beide Formen werden gelesen. Zeilen mit
 * Steuerkennzeichen, aber ohne Kennziffer, landen als **Klärungsfall** in der
 * Liste — nicht in einer Sammelzeile, die niemand mehr aufdröseln kann.
 *
 * Ein Kennzeichen der Steuerschuld des Empfängers (§ 13b UStG,
 * innergemeinschaftlicher Erwerb, MVP-1621) steht an der Aufwandszeile: Die
 * Grundlage geht in ihre Kennziffer (46/84/89/93), die Steuer in die
 * Kennziffer der Umsatzsteuer (47/85) und zugleich in die der Vorsteuer (67/61).
 *
 * Ausgewiesen werden Bemessungsgrundlagen und Steuerbeträge, nicht der
 * amtliche Vordruck: Der Abgleich mit der Erklärung bleibt Sache der
 * Steuerberatung.
 */
class VatFieldBreakdownService {
    private const array POSTED = [AccountingEntryStatus::Posted->value, AccountingEntryStatus::Reversed->value];

    /**
     * @return array{fields: list<array<string, mixed>>, unclear: list<string>}
     */
    public function forRange(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $lines = AccountingEntryLine::query()
            ->where('accounting_entry_lines.organization_id', $organization->id)
            ->whereNotNull('accounting_tax_code_id')
            ->whereHas('entry', fn ($query) => $query
                ->whereIn('status', self::POSTED)
                ->whereDate('booked_on', '>=', $from->toDateString())
                ->whereDate('booked_on', '<=', $to->toDateString()))
            ->with('taxCode')
            ->get();

        /** @var array<string, array{field: string, base: numeric-string, tax: numeric-string, direction: string}> $fields */
        $fields = [];
        $unclear = [];

        // Je Buchung zählt ein Kennzeichen in genau einer Form: Steht es an einer
        // Nettozeile, ist eine Steuerzeile mit demselben Kennzeichen schon enthalten.
        $netForm = [];
        foreach ($lines as $line) {
            $code = $line->taxCode;
            if ($code instanceof AccountingTaxCode && ! $this->isTaxLine($line, $code)) {
                $netForm[$line->accounting_entry_id . ':' . $code->id] = true;
            }
        }

        foreach ($lines as $line) {
            $code = $line->taxCode;
            if (! $code instanceof AccountingTaxCode) {
                continue;
            }
            if ($this->isTaxLine($line, $code) && isset($netForm[$line->accounting_entry_id . ':' . $code->id])) {
                continue;
            }

            $isInput = $code->direction->basedOnDebit();

            // Bemessungsgrundlage: der Netto-Anteil der Zeile. Die Steuer steht
            // in `tax_amount`, den Rest trägt die Zeile selbst. Haben − Soll
            // für Ausgangsumsätze, Soll − Haben für Vorsteuerfälle.
            $signed = $line->signedAmount();
            $base = ($isInput ? $signed : $signed->negated())->getAmount();
            $tax = $line->tax_amount?->getAmount() ?? '0.00';

            // Ältere Buchungen aus der Inbox tragen das Kennzeichen an der Steuerzeile
            // (Regelrolle Umsatz-/Vorsteuer): Die Zeile IST dann die Steuer, ihr
            // Betrag wäre als Bemessungsgrundlage falsch. Die Grundlage wird aus
            // Steuer und Satz zurückgerechnet — Festbuchungen bleiben unberührt.
            if ($this->isTaxLine($line, $code)) {
                $tax = $line->tax_amount?->getAmount() ?? $base;
                $base = NumberHelper::isZeroPrecise((string) $code->rate)
                    ? '0.00'
                    : NumberHelper::dividePrecise(NumberHelper::multiplyPrecise($tax, '100', 2), (string) $code->rate, 2, RoundingMode::HalfUp);
            }

            if ($code->ustva_base_field === null && $code->ustva_tax_field === null && $code->ustva_input_tax_field === null) {
                $unclear[$code->code] = (string) __('accounting.filing.fields.unclear', ['code' => $code->code . ' — ' . $code->name]);

                continue;
            }

            $selfAssessed = $code->direction === TaxCodeDirection::SelfAssessed;
            $slots = [
                [$code->ustva_base_field, $base, 'base', $selfAssessed ? 'output' : null],
                [$code->ustva_tax_field, $tax, 'tax', $selfAssessed ? 'output' : null],
                [$selfAssessed ? $code->ustva_input_tax_field : null, $tax, 'tax', 'input'],
            ];
            foreach ($slots as [$field, $value, $slot, $direction]) {
                if ($field === null || $field === '') {
                    continue;
                }

                $fields[$field] ??= [
                    'field' => $field,
                    'base' => '0.00',
                    'tax' => '0.00',
                    'direction' => $direction ?? ($isInput ? 'input' : 'output'),
                ];
                $fields[$field][$slot] = NumberHelper::addPrecise($fields[$field][$slot], $value, 2);
            }
        }

        ksort($fields);

        return ['fields' => array_values($fields), 'unclear' => array_values($unclear)];
    }

    /** Zeile auf einem Steuerkonto ihres Kennzeichens — die ältere Form der Inbox-Buchungen. */
    private function isTaxLine(AccountingEntryLine $line, AccountingTaxCode $code): bool {
        return $code->isTaxAccount((int) $line->accounting_account_id);
    }
}
