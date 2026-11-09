<?php
/*
 * Created on   : Sun Jul 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValueObjectCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Casts;

use App\Casts\Concerns\EncryptsAtRest;
use App\Exceptions\UnparseableValueObjectException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Basis für Casts auf `CommonToolkit\ValueObjects\*`.
 *
 * Die Spalte behält ihren Typ — gespeichert wird immer die kanonische
 * Schreibweise des Value Objects, gelesen wird das Objekt. Ungültige Werte
 * ergeben beim Lesen `null` statt einer Exception, sonst wäre ein einziger
 * Altbestandssatz genug, um eine Liste unrenderbar zu machen.
 *
 * Mit der Option `encrypted` übernimmt der Cast zusätzlich die
 * Ver-/Entschlüsselung ({@see EncryptsAtRest}). Kennungen (IBAN, USt-IdNr. …)
 * lesen dagegen als Text: {@see IdentifierCast}.
 *
 * @template TValue of object
 *
 * @implements CastsAttributes<TValue, TValue|string|null>
 */
abstract class ValueObjectCast implements CastsAttributes {
    use EncryptsAtRest;

    /**
     * Rohwert → Value Object; `null`, wenn der Wert die Prüfung nicht besteht.
     *
     * @param  array<string, mixed>  $attributes
     * @return TValue|null
     */
    abstract protected function toValueObject(string $raw, Model $model, array $attributes): ?object;

    /**
     * Value Object → kanonischer Spaltenwert.
     *
     * @param  TValue  $value
     */
    abstract protected function toStorage(object $value): string;

    /**
     * @param  array<string, mixed>  $attributes
     * @return TValue|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?object {
        $raw = $this->read($value);

        return $raw === null ? null : $this->toValueObject($raw, $model, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array {
        if ($value === null || $value === '') {
            return [$key => null];
        }

        // Ungültige Eingaben werden getrimmt durchgereicht statt verworfen —
        // Pflicht zur Gültigkeit gehört in den Form-Request, nicht in den Cast.
        // Ausnahme: numerische Casts (rejectsUnparseable), deren Rohtext sonst
        // still in einer DECIMAL-Spalte landete (Wertobjekt-Audit 2026-09-19).
        if (is_object($value)) {
            $stored = $this->toStorage($value);
        } else {
            $raw = trim((string) $value);
            if ($this->rejectsUnparseable() && $this->toValueObject($raw, $model, $attributes) === null) {
                throw new UnparseableValueObjectException($key, $raw, Str::before(class_basename(static::class), 'Cast'));
            }
            $stored = $this->storeScalar($raw, $model, $attributes);
        }

        return [$key => $this->write($stored)];
    }

    /**
     * Nicht deutbare Skalare beim Schreiben ablehnen statt roh durchreichen.
     * Standard aus: Altbestand und Importe dürfen Ungültiges tragen, geprüft
     * wird im Form-Request.
     */
    protected function rejectsUnparseable(): bool {
        return false;
    }

    /**
     * Skalare Eingabe → Spaltenwert: kanonisiert, wenn gültig, sonst unverändert.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function storeScalar(string $raw, Model $model, array $attributes): string {
        $vo = $this->toValueObject($raw, $model, $attributes);

        return $vo === null ? $raw : $this->toStorage($vo);
    }

    /**
     * Kanonische Spaltenform für den Dirty-Vergleich numerischer Casts: SQLite
     * liefert DECIMAL als Zahl (`19`), MariaDB als String (`19.00`).
     */
    protected function canonical(Model $model, mixed $value): ?string {
        $raw = $this->read($value);

        return $raw === null ? null : $this->storeScalar(trim($raw), $model, $model->getAttributes());
    }

    /** Erste rein numerische Option — die Nachkommastellen der Spalte. */
    protected function scaleOption(): ?int {
        foreach ($this->options as $option) {
            if (ctype_digit($option)) {
                return (int) $option;
            }
        }

        return null;
    }
}
