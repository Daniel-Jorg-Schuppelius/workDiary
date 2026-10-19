<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZoneFields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition;

/**
 * Felder, die eine Zone liefern kann (Phase 151): Schlüssel im Editor,
 * Auswerteart, Schlüssel im Erkennungsergebnis und Spalte am Eingang.
 */
final class ZoneFields {
    /** @var array<string, array{parse: string, result: string, column: string}> */
    public const FIELDS = [
        'invoice_number' => ['parse' => 'text', 'result' => 'number', 'column' => 'invoice_number'],
        'issue_date' => ['parse' => 'date', 'result' => 'issued_on', 'column' => 'issue_date'],
        'due_date' => ['parse' => 'date', 'result' => 'due_on', 'column' => 'due_date'],
        'amount_net' => ['parse' => 'money', 'result' => 'net', 'column' => 'amount_net'],
        'amount_tax' => ['parse' => 'money', 'result' => 'tax', 'column' => 'amount_tax'],
        'amount_gross' => ['parse' => 'money', 'result' => 'gross', 'column' => 'amount_gross'],
        'seller_vat_id' => ['parse' => 'vat_id', 'result' => 'seller_vat', 'column' => 'seller_vat_id'],
        'seller_name' => ['parse' => 'text', 'result' => 'seller', 'column' => 'seller_name'],
    ];

    public static function label(string $field): string {
        return match ($field) {
            'invoice_number' => (string) __('Rechnungsnummer'),
            'issue_date' => (string) __('Rechnungsdatum'),
            'due_date' => (string) __('Fällig am'),
            'amount_net' => (string) __('Netto'),
            'amount_tax' => (string) __('Steuer'),
            'amount_gross' => (string) __('Brutto'),
            'seller_vat_id' => (string) __('USt-IdNr.'),
            'seller_name' => (string) __('Verkäufer'),
            default => $field,
        };
    }

    /** @return array<string, string> Feld → Label */
    public static function labels(): array {
        $labels = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $labels[$field] = self::label($field);
        }

        return $labels;
    }

    public static function parseOf(string $field): string {
        return self::FIELDS[$field]['parse'] ?? 'text';
    }
}
