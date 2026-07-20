<?php
/*
 * Created on   : Sun Jul 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesActorId.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Löst den Akteur für Audit-/Event-Zeilen auf: ein explizit übergebener
 * User gewinnt, sonst der eingeloggte Benutzer, sonst null (CLI/Queue).
 *
 * Vollaudit 2026-07 (N39): ersetzt 7 byte-gleiche Kopien. Die frühere
 * Finance-Variante ($actor->id ?? Auth::id()) unterschied sich nur für
 * nicht-persistierte Akteure (id null) — maßgeblich ist der übergebene
 * Akteur; für persistierte User sind beide Varianten identisch.
 */
trait ResolvesActorId {
    private function resolveActorId(?User $actor): ?int {
        if ($actor instanceof User) {
            return (int) $actor->id;
        }
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }
}
