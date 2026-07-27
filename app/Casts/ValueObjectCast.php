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

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Basis für Casts auf `CommonToolkit\ValueObjects\*`.
 *
 * Die Spalte behält ihren Typ — gespeichert wird immer die kanonische
 * Schreibweise des Value Objects, gelesen wird das Objekt. Ungültige Werte
 * ergeben beim Lesen `null` statt einer Exception, sonst wäre ein einziger
 * Altbestandssatz genug, um eine Liste unrenderbar zu machen.
 *
 * Mit der Option `encrypted` übernimmt der Cast zusätzlich die
 * Ver-/Entschlüsselung, weil Laravel Casts nicht stapeln kann:
 * `'iban' => IbanCast::class . ':encrypted'`.
 *
 * @template TValue of object
 *
 * @implements CastsAttributes<TValue, TValue|string|null>
 */
abstract class ValueObjectCast implements CastsAttributes {
    /** @var array<array-key, string> */
    protected array $options;

    public function __construct(string ...$options) {
        $this->options = $options;
    }

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
        $stored = is_object($value)
            ? $this->toStorage($value)
            : $this->storeScalar(trim((string) $value), $model, $attributes);

        return [$key => $this->write($stored)];
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

    protected function hasOption(string $option): bool {
        return in_array($option, $this->options, true);
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

    /** Spaltenwert → Klartext; `null` bei leer. */
    protected function read(mixed $value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = (string) $value;
        if (!$this->hasOption('encrypted')) {
            return $raw;
        }

        try {
            $plain = Crypt::decryptString($raw);
        } catch (DecryptException) {
            // Altbestand vor der Verschlüsselung: Klartext durchreichen.
            return $raw;
        }

        return $plain === '' ? null : $plain;
    }

    /** Klartext → Spaltenwert. */
    protected function write(string $plain): string {
        return $this->hasOption('encrypted') ? Crypt::encryptString($plain) : $plain;
    }
}
