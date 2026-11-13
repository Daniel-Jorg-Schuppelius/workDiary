<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialStockException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * Eine Lagerregel verhindert die Bestandsbuchung eines Materialverbrauchs
 * (MVP-1679): Bestand reicht nicht, Seriennummer nicht angebbar, Einheit
 * nicht gepflegt, Lager gesperrt. Online ein Eingabefehler am Feld, offline
 * ein Konflikt statt einer stillen Buchung. Nur ein fehlender Bestand lässt
 * sich mit „Negativen Bestand zulassen“ auflösen (MVP-1691).
 */
final class MaterialStockException extends ValidationException {
    private bool $shortage = false;

    public static function on(string $field, string $message): self {
        return self::withMessages([$field => $message]);
    }

    /** Der verfügbare Bestand reicht nicht — Fehler an der Menge. */
    public static function shortage(string $message): self {
        $exception = self::on('quantity', $message);
        $exception->shortage = true;

        return $exception;
    }

    public function isShortage(): bool {
        return $this->shortage;
    }

    public function field(): string {
        return (string) array_key_first($this->errors());
    }

    public function reason(): string {
        $messages = $this->errors()[$this->field()] ?? [];

        return (string) ($messages[0] ?? $this->getMessage());
    }
}
