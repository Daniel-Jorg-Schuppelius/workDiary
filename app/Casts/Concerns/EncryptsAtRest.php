<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EncryptsAtRest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Casts\Concerns;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Cast-Optionen und Verschlüsselung at rest für Wertobjekt- und Kennungs-Casts:
 * Laravel kann Casts nicht stapeln, deshalb übernimmt der Cast mit der Option
 * `encrypted` die Ver-/Entschlüsselung selbst. Rekey und Audit-Schwärzung
 * erkennen die Option über {@see \App\Support\Crypto\EncryptedCasts}.
 */
trait EncryptsAtRest {
    /** @var array<array-key, string> */
    protected array $options;

    public function __construct(string ...$options) {
        $this->options = $options;
    }

    protected function hasOption(string $option): bool {
        return in_array($option, $this->options, true);
    }

    /**
     * Spaltenwert → Klartext; `null` bei leer. Klartext aus der Zeit vor der
     * Verschlüsselung kommt durch; ein Laravel-Chiffrat, das sich nicht
     * entschlüsseln lässt (falscher APP_KEY), wirft wie der `encrypted`-Cast,
     * statt den Chiffretext als Wert auszugeben.
     */
    protected function read(mixed $value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = (string) $value;
        if (! $this->hasOption('encrypted')) {
            return $raw;
        }

        try {
            $plain = Crypt::decryptString($raw);
        } catch (DecryptException $e) {
            // Base64 von `{"iv":` — so beginnt jedes Laravel-Chiffrat, nie eine Kennung.
            if (str_starts_with($raw, 'eyJpdiI6')) {
                throw $e;
            }

            return $raw;
        }

        return $plain === '' ? null : $plain;
    }

    /** Klartext → Spaltenwert. */
    protected function write(string $plain): string {
        return $this->hasOption('encrypted') ? Crypt::encryptString($plain) : $plain;
    }
}
