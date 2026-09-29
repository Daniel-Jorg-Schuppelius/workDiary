<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExchangeRateService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\AccountingExchangeRate;
use App\Models\Platform\{Organization, User};
use App\Support\Query\DateRange;
use App\Support\Toolkit\CsvFacade;
use Carbon\{CarbonImmutable, CarbonInterface};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Monatskurse für Fremdwährungsbelege (Feature 125, MVP-1012). */
class ExchangeRateService {
    public const DEFAULT_SOURCE = 'BMF';

    public function rateFor(Organization $organization, CurrencyCode $currency, CarbonInterface $on): ?AccountingExchangeRate {
        $month = CarbonImmutable::instance($on)->startOfMonth();

        return AccountingExchangeRate::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('currency', $currency->value)
            ->whereBetween('period', DateRange::days($month, $month))
            ->first();
    }

    /** Legt den Kurs eines Monats an oder ersetzt ihn. */
    public function save(Organization $organization, User $actor, CurrencyCode $currency, CarbonInterface $month, Decimal $rate, ?string $source): AccountingExchangeRate {
        if (! $rate->isPositive()) {
            throw ValidationException::withMessages(['rate' => (string) __('accounting.exchange_rates.error.rate')]);
        }
        $existing = $this->rateFor($organization, $currency, $month);
        $values = ['rate' => $rate, 'source' => $source !== null && trim($source) !== '' ? trim($source) : self::DEFAULT_SOURCE, 'updated_by' => $actor->id];
        if ($existing instanceof AccountingExchangeRate) {
            $existing->update($values);

            return $existing;
        }

        return AccountingExchangeRate::query()->create($values + [
            'organization_id' => $organization->id,
            'currency' => $currency,
            'period' => CarbonImmutable::instance($month)->startOfMonth()->toDateString(),
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Zeilen „Währung;Monat;Kurs“ (Monat als 2026-03 oder 03/2026), Semikolon
     * oder Tabulator; eine fehlerhafte Zeile verwirft den ganzen Import.
     */
    public function import(Organization $organization, User $actor, string $text, ?string $source): int {
        $rows = [];
        foreach (preg_split('/\R/u', trim($text)) ?: [] as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = array_values(array_filter(array_map('trim', CsvFacade::parseRows(str_replace("\t", ';', $line), ';')[0] ?? []), static fn (string $cell): bool => $cell !== ''));
            $currency = CurrencyCode::tryFrom(strtoupper($cells[0] ?? ''));
            $month = $this->month($cells[1] ?? '');
            $rate = $this->decimal($cells[2] ?? '');
            if ($currency === null || $month === null || $rate === null || ! $rate->isPositive()) {
                throw ValidationException::withMessages(['import' => (string) __('accounting.exchange_rates.error.line', ['line' => $index + 1])]);
            }
            $rows[] = [$currency, $month, $rate];
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['import' => (string) __('accounting.exchange_rates.error.empty')]);
        }

        return DB::transaction(function () use ($organization, $actor, $rows, $source): int {
            foreach ($rows as [$currency, $month, $rate]) {
                $this->save($organization, $actor, $currency, $month, $rate, $source);
            }

            return count($rows);
        });
    }

    private function month(string $value): ?CarbonImmutable {
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $value, $m) === 1 || preg_match('/^(\d{1,2})[\/.](\d{4})$/', $value, $n) === 1) {
            [$year, $month] = isset($m[1]) ? [(int) $m[1], (int) $m[2]] : [(int) $n[2], (int) $n[1]];

            return $month >= 1 && $month <= 12 ? CarbonImmutable::create($year, $month, 1) : null;
        }

        return null;
    }

    /** Ein einzelnes Trennzeichen ist das Dezimalzeichen (BMF: „1,0823“); beide zusammen folgen der Toolkit-Erkennung. */
    private function decimal(string $value): ?Decimal {
        $value = str_replace(' ', '', $value);
        $normalized = str_contains($value, ',') && str_contains($value, '.')
            ? NumberHelper::normalizeDecimalStringOrNull($value)
            : str_replace(',', '.', $value);

        return $normalized !== null && is_numeric($normalized) ? Decimal::of($normalized, 6) : null;
    }
}
