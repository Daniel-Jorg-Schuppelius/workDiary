<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlRegistrationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Enums;

/**
 * Stand der App-Registrierung in der Wawi (OnPremise, MVP-316). Eigene
 * Abbildung des numerischen API-Status; leer, solange keine Registrierung läuft.
 */
enum JtlRegistrationStatus: string {
    case Pending = 'pending';
    case Rejected = 'rejected';
    case Accepted = 'accepted';
}
