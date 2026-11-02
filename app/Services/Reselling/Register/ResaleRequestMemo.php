<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleRequestMemo.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use Closure;

/**
 * Request-Memo des Reselling-Registers (Leistungsanalyse 2026-11-02, perfc-01/04/05/11):
 * scoped gebunden (AppServiceProvider), also je Request bzw. Job frisch — die
 * Singletons (Belegspiegel, Plan-Quellen) halten nichts selbst. Hier liegen die
 * Spiegelzeilen der Organisation, die Serienvorlagen, die Lexoffice-Kontaktkarte
 * und die Abos der Lizenzkonten. Wer solche Daten im selben Prozess ändert
 * (Sync, Import), ruft {@see forget()}.
 */
final class ResaleRequestMemo {
    /** @var array<string, mixed> */
    private array $values = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $loader
     * @return T
     */
    public function remember(string $key, Closure $loader): mixed {
        if (! array_key_exists($key, $this->values)) {
            $this->values[$key] = $loader();
        }

        return $this->values[$key];
    }

    public function has(string $key): bool {
        return array_key_exists($key, $this->values);
    }

    /** Alles vergessen — oder nur die Einträge mit diesem Präfix. */
    public function forget(?string $prefix = null): void {
        if ($prefix === null) {
            $this->values = [];

            return;
        }
        foreach (array_keys($this->values) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->values[$key]);
            }
        }
    }
}
