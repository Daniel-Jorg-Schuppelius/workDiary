<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGuardianPermission.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Erlaubte Handlungen einer Vertretung (Sorgeberechtigte, MVP-842) für ein
 * betreutes Mitglied. Vertretung für Anmeldungen berechtigt nicht zu
 * Beitragsdaten — die kommen mit MVP-849 als eigene Berechtigung.
 */
enum ClubGuardianPermission: string implements HasLabel {
    use HasOptions;

    case Register = 'register';
    case ViewAttendance = 'view_attendance';
    case ReceiveMessages = 'receive_messages';

    public function label(): string {
        return (string) __('enums.club.guardian-permission.' . $this->value);
    }
}
