<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdentifierCast.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Casts;

use App\Casts\Concerns\EncryptsAtRest;
use Illuminate\Contracts\Database\Eloquent\{CastsAttributes, ComparesCastableAttributes};
use Illuminate\Database\Eloquent\Model;

/**
 * Basis der Kennungs-Casts (IBAN, BIC, USt-IdNr., Steuernummer, Steuer-ID,
 * GTIN; MVP-1512). Geschrieben wird die kanonische Form des Wertobjekts,
 * gelesen der gespeicherte Text unverändert: Ein Objekt oder ein `null` für
 * Ungültiges bräche Exporte, XRechnung, DATEV-Stammdaten und Audit-Diffs, und
 * ein ungültiger Altbestand muss lesbar bleiben. Ungültige Eingaben bleiben
 * getrimmt erhalten, die Pflicht zur Gültigkeit liegt im Form-Request.
 *
 * @template TValue of object
 *
 * @implements CastsAttributes<string, TValue|string|null>
 */
abstract class IdentifierCast implements CastsAttributes, ComparesCastableAttributes {
    use EncryptsAtRest;

    /** @return TValue|null `null`, wenn der Wert die Prüfung nicht besteht */
    abstract protected function toValueObject(string $raw): ?object;

    /** @param  TValue  $value */
    abstract protected function toStorage(object $value): string;

    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string {
        return $this->read($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array {
        if ($value === null) {
            return [$key => null];
        }
        $plain = is_object($value) ? $this->toStorage($value) : $this->normalise(trim((string) $value));

        return [$key => $plain === '' ? null : $this->write($plain)];
    }

    /** Chiffrate gleicher Klartexte sind verschieden; ohne Vergleich gälte jedes Speichern als Änderung. */
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool {
        return $this->read($firstValue) === $this->read($secondValue);
    }

    /** Gültig → kanonisch, sonst unverändert. */
    protected function normalise(string $raw): string {
        $valueObject = $raw === '' ? null : $this->toValueObject($raw);

        return $valueObject === null ? $raw : $this->toStorage($valueObject);
    }
}
