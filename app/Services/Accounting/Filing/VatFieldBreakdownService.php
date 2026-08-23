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
use App\Models\Organization;
use Carbon\CarbonImmutable;

/**
 * Aufteilung nach den Kennziffern der Voranmeldung (Feature 125, MVP-688).
 *
 * Die Zuordnung kommt vom Steuerkennzeichen der Buchungszeile. Zeilen mit
 * Steuerkennzeichen, aber ohne Kennziffer, landen als **Klärungsfall** in der
 * Liste — nicht in einer Sammelzeile, die niemand mehr aufdröseln kann.
 *
 * Ausgewiesen werden Bemessungsgrundlagen und Steuerbeträge, nicht der
 * amtliche Vordruck: Der Abgleich mit der Erklärung bleibt Sache der
 * Steuerberatung.
 */
class VatFieldBreakdownService {
    private const POSTED = [AccountingEntryStatus::Posted->value, AccountingEntryStatus::Reversed->value];

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

        /** @var array<string, array{field: string, base: string, tax: string, direction: string}> $fields */
        $fields = [];
        $unclear = [];

        foreach ($lines as $line) {
            $code = $line->taxCode;
            if (! $code instanceof AccountingTaxCode) {
                continue;
            }

            $isInput = $code->direction === TaxCodeDirection::Input;

            // Bemessungsgrundlage: der Netto-Anteil der Zeile. Die Steuer steht
            // in `tax_amount`, den Rest trägt die Zeile selbst.
            $amount = (float) ($line->credit?->getAmount() ?? '0.00') - (float) ($line->debit?->getAmount() ?? '0.00');
            $base = $isInput ? -$amount : $amount;
            $tax = (float) ($line->tax_amount?->getAmount() ?? '0.00');

            if ($code->ustva_base_field === null && $code->ustva_tax_field === null) {
                $unclear[$code->code] = (string) __('accounting.filing.fields.unclear', ['code' => $code->code . ' — ' . $code->name]);

                continue;
            }

            foreach ([[$code->ustva_base_field, $base, 'base'], [$code->ustva_tax_field, $tax, 'tax']] as [$field, $value, $slot]) {
                if ($field === null || $field === '') {
                    continue;
                }

                $fields[$field] ??= [
                    'field' => $field,
                    'base' => '0.00',
                    'tax' => '0.00',
                    'direction' => $isInput ? 'input' : 'output',
                ];
                $fields[$field][$slot] = number_format((float) $fields[$field][$slot] + $value, 2, '.', '');
            }
        }

        ksort($fields);

        return ['fields' => array_values($fields), 'unclear' => array_values($unclear)];
    }
}
