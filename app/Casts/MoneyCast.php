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

use App\Exceptions\UnparseableValueObjectException;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\{CastsAttributes, ComparesCastableAttributes};
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
class MoneyCast implements CastsAttributes, ComparesCastableAttributes {
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

        // Lesen bleibt nachsichtig (wie ValueObjectCast): ein unlesbarer Altwert
        // darf keine Liste unrenderbar machen — er ergibt null.
        return Money::ofNullable((string) $value, $this->currency($model, $attributes), $this->scale());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array {
        if ($value === null || $value === '') {
            return [$key => null];
        }

        // Schreiben ist streng: Money::of() wirft seit common-toolkit 2.0 bei
        // unlesbarer Eingabe, statt still 0 zu speichern.
        try {
            $money = $value instanceof Money
                ? $value
                : Money::of((string) $value, $this->currency($model, $attributes), $this->scale());
        } catch (\InvalidArgumentException) {
            throw new UnparseableValueObjectException($key, (string) $value, 'Money');
        }

        return [$key => $money->getAmount()];
    }

    /**
     * Gleichheit für die Dirty-Prüfung (`isDirty`/`wasChanged`/Audit-Diff).
     *
     * SQLite liefert `decimal`-Spalten als Float (741.6), der Cast schreibt den
     * Dezimalstring ('741.60'); Eloquent vergliche sonst die Strings und hielte
     * jede unveränderte Zeile für geändert — MariaDB liefert Strings, deshalb
     * fällt das nur in der CI auf. Verglichen wird der kanonische Betrag.
     */
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool {
        return $this->canonical($model, $firstValue) === $this->canonical($model, $secondValue);
    }

    /** Kanonischer Betrag der Spalte; nicht-numerischer Fremdwert bleibt, wie er ist. */
    private function canonical(Model $model, mixed $value): ?string {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof Money) {
            return $value->getAmount();
        }
        if (!is_numeric($value)) {
            return (string) $value;
        }

        return Money::of((string) $value, $this->currency($model, $model->getAttributes()), $this->scale())->getAmount();
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
