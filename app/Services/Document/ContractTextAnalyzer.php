<?php
/*
 * Created on   : Sat Sep 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractTextAnalyzer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Document;

use CommonToolkit\Enums\CountryCode;
use CommonToolkit\Helper\Data\{DateHelper, NumberHelper, StringHelper};

/**
 * Vorschläge für Vertragsfelder aus dem Text eines Vertragsdokuments (MVP-906),
 * für Leasing- und Finanzierungsverträge ergänzt um Rate, Sonderzahlung,
 * Restwert und Kaufoption (MVP-934).
 * Erkennt übliche deutsche Klauseln zu Beginn, Ende, Mindestlaufzeit,
 * Kündigungsfrist, Verlängerung und Entgelt. Liefert nur Vorschläge mit
 * Fundstelle; übernommen wird erst durch Speichern des Formulars — keine
 * automatische Vertragsauslegung.
 */
final class ContractTextAnalyzer {
    private const DATE = '(\d{1,2}\.\s?\d{1,2}\.\s?\d{2,4})';

    private const AMOUNT = '(\d{1,3}(?:\.\d{3})*,\d{2}|\d+(?:,\d{2})?)';

    private const COUNT = '(\d{1,3}|eine[nm]?|ein|zwei|drei|vier|fünf|sechs|sieben|acht|neun|zehn|elf|zwölf|vierundzwanzig|sechsunddreißig)';

    private const WORDS = ['ein' => 1, 'eine' => 1, 'einen' => 1, 'einem' => 1, 'zwei' => 2, 'drei' => 3, 'vier' => 4, 'fünf' => 5, 'sechs' => 6, 'sieben' => 7, 'acht' => 8, 'neun' => 9, 'zehn' => 10, 'elf' => 11, 'zwölf' => 12, 'vierundzwanzig' => 24, 'sechsunddreißig' => 36];

    /**
     * @return array{fields: array<string, mixed>, hints: list<array{field: string, snippet: string}>}
     */
    public function analyze(string $text): array {
        $text = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $fields = [];
        $hints = [];
        $found = function (string $field, mixed $value, string $snippet) use (&$fields, &$hints): void {
            if (array_key_exists($field, $fields) || $value === null) {
                return;
            }
            $fields[$field] = $value;
            $hints[] = ['field' => $field, 'snippet' => StringHelper::normalizeWhitespace($snippet, true)];
        };

        foreach ([
            '/(?:vertragsbeginn|mietbeginn|leistungsbeginn|beginnt|beginn)\s*(?:ist|am|ab|zum|:)?\s*(?:dem\s+)?' . self::DATE . '/iu',
            '/(?:gültig|wirksam|in kraft)\s+ab\s+(?:dem\s+)?' . self::DATE . '/iu',
        ] as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                $found('starts_on', $this->date($m[1]), $m[0]);
            }
        }
        foreach ([
            '/(?:endet|vertragsende|befristet\s+bis|läuft\s+bis)\s*(?:am|zum|:)?\s*(?:dem\s+)?' . self::DATE . '/iu',
        ] as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                $found('ends_on', $this->date($m[1]), $m[0]);
            }
        }
        if (preg_match('/(?:mindestlaufzeit|mindestvertragslaufzeit|grundlaufzeit|laufzeit)\s*(?:von|beträgt|:)?\s*' . self::COUNT . '\s*(monat\w*|jahr\w*)/iu', $text, $m) === 1) {
            $found('min_term_months', $this->months($m[1], $m[2]), $m[0]);
        }
        if (preg_match('/(?:kündigungsfrist\s*(?:von|beträgt|:)?|frist\s+von)\s*' . self::COUNT . '\s*(tag\w*|woche\w*|monat\w*)/iu', $text, $m) === 1) {
            $found('notice_period_days', $this->days($m[1], $m[2]), $m[0]);
        }
        if (preg_match('/verlängert\s+sich\s+(?:\w+\s+){0,3}?um\s+(?:(?:jeweils|weitere)\s+)?' . self::COUNT . '\s*(monat\w*|jahr\w*)/iu', $text, $m) === 1) {
            $months = $this->months($m[1], $m[2]);
            $found('renew_period_months', $months, $m[0]);
            if ($months !== null) {
                $found('auto_renew', true, $m[0]);
            }
        }
        if (preg_match('/(\d{1,3}(?:\.\d{3})*,\d{2}|\d+(?:,\d{2})?)\s*(?:€|eur\b|euro\b)[^.\n]{0,40}?(monatlich|pro monat|je monat|mtl\.|vierteljährlich|pro quartal|jährlich|pro jahr|p\.\s?a\.)/iu', $text, $m) === 1
            || preg_match('/(monatlich|vierteljährlich|jährlich)[^.\n]{0,40}?(\d{1,3}(?:\.\d{3})*,\d{2}|\d+(?:,\d{2})?)\s*(?:€|eur\b|euro\b)/iu', $text, $m) === 1) {
            [$raw, $period] = is_numeric(str_replace(['.', ','], '', $m[1])) ? [$m[1], $m[2]] : [$m[2], $m[1]];
            $amount = NumberHelper::normalizeDecimalStringOrNull($raw, CountryCode::Germany);
            if ($amount !== null) {
                $found('value_amount', $amount, $m[0]);
                $found('value_period', $this->period($period), $m[0]);
            }
        }
        if (isset($fields['ends_on']) || isset($fields['min_term_months'])) {
            $fields['term_kind'] ??= 'fixed';
        }

        return ['fields' => $fields, 'hints' => $hints];
    }

    /**
     * Vorschläge für die Leasingakte: Beginn, Ende (sonst Beginn + Laufzeit),
     * Rate mit Rhythmus, Sonderzahlung, Restwert, Kaufoption, Kündigungsfrist.
     *
     * @return array{fields: array<string, mixed>, hints: list<array{field: string, snippet: string}>}
     */
    public function leasing(string $text): array {
        $base = $this->analyze($text);
        $text = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $fields = array_intersect_key($base['fields'], array_flip(['starts_on', 'ends_on', 'notice_period_days']));
        $hints = array_values(array_filter($base['hints'], static fn (array $h): bool => isset($fields[$h['field']])));
        $found = function (string $field, mixed $value, string $snippet) use (&$fields, &$hints): void {
            if (array_key_exists($field, $fields) || $value === null) {
                return;
            }
            $fields[$field] = $value;
            $hints[] = ['field' => $field, 'snippet' => StringHelper::normalizeWhitespace($snippet, true)];
        };

        if (preg_match('/(?:leasingbeginn|übernahme\s+am|übernahmedatum)\s*(?:ist|am|:)?\s*(?:dem\s+)?' . self::DATE . '/iu', $text, $m) === 1) {
            $found('starts_on', $this->date($m[1]), $m[0]);
        }
        foreach ([
            'special_payment' => '/(?:leasingsonderzahlung|sonderzahlung|anzahlung)\s*(?:von|beträgt|in\s+höhe\s+von|:)?\s*' . self::AMOUNT . '\s*(?:€|eur\b|euro\b)/iu',
            'residual_value' => '/(?:kalkulierter\s+restwert|restwert)\s*(?:von|beträgt|in\s+höhe\s+von|:)?\s*' . self::AMOUNT . '\s*(?:€|eur\b|euro\b)/iu',
            'purchase_option_amount' => '/(?:kaufoption|andienungsrecht|übernahmepreis)\s*(?:zu|von|über|beträgt|in\s+höhe\s+von|:)?\s*' . self::AMOUNT . '\s*(?:€|eur\b|euro\b)/iu',
        ] as $field => $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                $found($field, NumberHelper::normalizeDecimalStringOrNull($m[1], CountryCode::Germany), $m[0]);
            }
        }
        if (preg_match('/(?:leasingrate|monatsrate|monatliche\s+rate|finanzierungsrate|rate)\s*(?:von|beträgt|in\s+höhe\s+von|:)?\s*' . self::AMOUNT . '\s*(?:€|eur\b|euro\b)(?:[^.\n]{0,30}?(monatlich|vierteljährlich|pro\s+quartal|jährlich|pro\s+jahr))?/iu', $text, $m) === 1) {
            $found('rate_amount', NumberHelper::normalizeDecimalStringOrNull($m[1], CountryCode::Germany), $m[0]);
            $fields['payment_rhythm'] = $this->period($m[2] ?? '');
        } elseif (isset($base['fields']['value_amount'])) {
            $fields['rate_amount'] = $base['fields']['value_amount'];
            $fields['payment_rhythm'] = $base['fields']['value_period'] ?? 'monthly';
            foreach ($base['hints'] as $hint) {
                if ($hint['field'] === 'value_amount') {
                    $hints[] = ['field' => 'rate_amount', 'snippet' => $hint['snippet']];
                }
            }
        }
        $term = $base['fields']['min_term_months'] ?? null;
        if (is_int($term) && isset($fields['starts_on']) && ! isset($fields['ends_on'])) {
            $fields['ends_on'] = DateHelper::addToDate((string) $fields['starts_on'], -1, $term);
            foreach ($base['hints'] as $hint) {
                if ($hint['field'] === 'min_term_months') {
                    $hints[] = ['field' => 'ends_on', 'snippet' => $hint['snippet']];
                }
            }
        }

        return ['fields' => array_filter($fields, static fn (mixed $v): bool => $v !== null), 'hints' => $hints];
    }

    private function date(string $raw): ?string {
        $iso = DateHelper::normalizeToIso(StringHelper::removeWhitespace($raw));

        return $iso !== null ? substr($iso, 0, 10) : null;
    }

    private function count(string $raw): ?int {
        $raw = mb_strtolower(trim($raw));

        return ctype_digit($raw) ? (int) $raw : (self::WORDS[$raw] ?? null);
    }

    private function months(string $count, string $unit): ?int {
        $n = $this->count($count);

        return $n === null ? null : (str_starts_with(mb_strtolower($unit), 'jahr') ? $n * 12 : $n);
    }

    /** Tage; Wochen × 7, Monate × 30 (der Hinweis nennt die Fundstelle zum Gegenlesen). */
    private function days(string $count, string $unit): ?int {
        $n = $this->count($count);
        $unit = mb_strtolower($unit);

        return $n === null ? null : match (true) {
            str_starts_with($unit, 'woche') => $n * 7,
            str_starts_with($unit, 'monat') => $n * 30,
            default => $n,
        };
    }

    private function period(string $raw): string {
        $raw = mb_strtolower($raw);

        return match (true) {
            str_contains($raw, 'viertel') || str_contains($raw, 'quartal') => 'quarterly',
            str_contains($raw, 'jähr') || str_contains($raw, 'jahr') || str_contains($raw, 'p.') => 'yearly',
            default => 'monthly',
        };
    }
}
