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
    /**
     * @param  string|null  $currencyColumn  Währungsquelle: Spalte oder „relation.spalte“
     * @param  string|null  $scale  Nachkommastellen der Spalte; ohne Angabe die
     *                              der Währung (2). Spalten mit feinerer Auflösung
     *                              (Einzelpreise `decimal(12,4)`) MÜSSEN sie setzen,
     *                              sonst rundet der Cast beim Lesen auf Cent.
     */
    public function __construct(
        private readonly ?string $currencyColumn = null,
        private readonly ?string $scale = null,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money {
        if ($value === null || $value === '') {
            return null;
        }

        return Money::of((string) $value, $this->currency($model, $attributes), $this->scale());
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
            : Money::of((string) $value, $this->currency($model, $attributes), $this->scale());

        return [$key => $money->getAmount()];
    }

    /**
     * Währung der Spalte: erst die konfigurierte Quelle, dann eine
     * `currency`-Spalte, zuletzt der Euro.
     *
     * Positionen ohne eigene Währungsspalte holen sie über einen Relationspfad
     * vom Beleg: `MoneyCast::class . ':invoice.currency'`. Ausgewertet wird nur
     * eine **bereits geladene** Relation — ein Lazy-Load pro Zeile würde in
     * Listen N+1 Abfragen auslösen. Fremdwährungsbelege deshalb mit
     * `with('invoice')` laden.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function currency(Model $model, array $attributes): CurrencyCode {
        foreach ([$this->currencyColumn, 'currency', 'currency_code'] as $source) {
            if ($source === null) {
                continue;
            }

            $raw = str_contains($source, '.')
                ? $this->fromRelation($model, $source)
                : ($attributes[$source] ?? $model->getAttribute($source));

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

    /** Nachkommastellen der Spalte; null = Währungsvorgabe (2). */
    private function scale(): ?int {
        return $this->scale !== null && ctype_digit($this->scale) ? (int) $this->scale : null;
    }

    /** Währung aus einer geladenen Relation („invoice.currency“); sonst null. */
    private function fromRelation(Model $model, string $path): mixed {
        [$relation, $column] = explode('.', $path, 2);

        if (!$model->relationLoaded($relation)) {
            return null;
        }

        $related = $model->getRelation($relation);

        return $related instanceof Model ? $related->getAttribute($column) : null;
    }
}
