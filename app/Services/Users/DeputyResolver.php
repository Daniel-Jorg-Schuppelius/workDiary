<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeputyResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\Vacation\VacationStatus;
use App\Models\User;

/**
 * Benannte Stellvertretung (MVP-523): Der Deputy entscheidet, solange der
 * Vertretene abwesend ist. Aus dem User-Model gezogen (Vollscan 2026-08-23,
 * B22): dort liefen je Prinzipal ZWEI exists-Queries — jetzt eine Query mit
 * whereHas; nur die Rollenprüfung (isAdmin, Spatie) bleibt im Speicher.
 */
class DeputyResolver {
    public function actsAsDeputyForAbsentAdmin(User $user): bool {
        $today = now()->toDateString();

        return User::query()
            ->where('organization_id', $user->organization_id)
            ->where('deputy_user_id', $user->getKey())
            ->where(function ($query) use ($today): void {
                $query
                    ->whereHas('vacations', function ($vacation) use ($today): void {
                        $vacation
                            ->where('status', VacationStatus::Approved->value)
                            ->whereDate('start_date', '<=', $today)
                            ->whereDate('end_date', '>=', $today);
                    })
                    ->orWhereHas('sickLeaves', function ($sickLeave) use ($today): void {
                        $sickLeave
                            ->whereDate('start_date', '<=', $today)
                            ->whereDate('end_date', '>=', $today);
                    });
            })
            ->get()
            ->contains(static fn (User $principal): bool => $principal->isAdmin());
    }
}
