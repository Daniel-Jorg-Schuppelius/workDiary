<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeActor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Platform\User;

/**
 * Bearbeiter automatischer Eingänge (MVP-1144): Ablage und Audit brauchen eine
 * Person, auch wenn der Eingang keiner gehört. Vorrang hat, wer die Verbindung
 * angelegt hat; fehlt diese Person in der Organisation, die älteste aktive,
 * zuletzt die älteste überhaupt. Gilt für Rechnungspostfach, Cloud-Eingang und
 * Peppol, damit kein Kanal Belege still verliert, wenn der Anleger geht.
 */
final class IntakeActor {
    public static function resolve(int $organizationId, ?int $preferredUserId = null): ?User {
        $users = static fn () => User::query()->withoutGlobalScopes()->where('organization_id', $organizationId);

        if ($preferredUserId !== null && $preferredUserId > 0) {
            $preferred = $users()->whereKey($preferredUserId)->first();
            if ($preferred instanceof User) {
                return $preferred;
            }
        }

        return $users()->whereNull('deactivated_at')->orderBy('id')->first()
            ?? $users()->orderBy('id')->first();
    }
}
