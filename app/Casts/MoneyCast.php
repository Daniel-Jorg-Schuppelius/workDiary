<?php
/*
 * Created on   : Sun Jul 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MoneyCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Casts;

use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Geldspalte → {@see Money}.
 *
 * Die Datenbank hält `decimal(…,2)`; gelesen wird der exakte Dezimalstring, es
 * gibt also nie einen float-Zwischenschritt. Die Währung kommt aus einer
 * Schwesterspalte (Standard `currency`), sonst aus der Organisationswährung —
 * deshalb der Parameter: `#[…] 'total' => MoneyCast::class.':currency_code'`.
 *
 * Geschrieben wird wieder der kanonische Dezimalstring, damit die Spalte
 * unverändert `decimal` bleibt und Summen in SQL weiter funktionieren.
 *
 * @implements CastsAttributes<Money|null, Money|string|float|int|null>
 */
class MoneyCast implements CastsAttributes {
    public function __construct(private readonly ?string $currencyColumn = null) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money {
        if ($value === null || $value === '') {
            return null;
        }

        return Money::of((string) $value, $this->currency($model, $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array {
        if ($value === null || $value === '') {
            return [$key => null];
        }

        $money = $value instanceof Money
            ? $value
            : Money::of((string) $value, $this->currency($model, $attributes));

        return [$key => $money->getAmount()];
    }

    /**
     * Währung der Spalte: erst die konfigurierte Schwesterspalte, dann eine
     * `currency`-Spalte, zuletzt der Euro.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function currency(Model $model, array $attributes): CurrencyCode {
        foreach ([$this->currencyColumn, 'currency', 'currency_code'] as $column) {
            if ($column === null) {
                continue;
            }

            $raw = $attributes[$column] ?? $model->getAttribute($column);
            if ($raw instanceof CurrencyCode) {
                return $raw;
            }
            if (is_string($raw) && $raw !== '') {
                $resolved = CurrencyCode::tryFrom(strtoupper($raw));
                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        return CurrencyCode::Euro;
    }
}
