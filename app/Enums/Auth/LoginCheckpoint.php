<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginCheckpoint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Auth;

/**
 * Stelle, an der die Anmelderichtlinie geprüft wird (Feature 205): bei der
 * Anmeldung, je Anfrage einer laufenden Sitzung oder je Anfrage mit einem
 * persönlichen API-Token. Rein technisch, ohne Anzeige.
 */
enum LoginCheckpoint: string {
    case Login = 'login';
    case Session = 'session';
    case Token = 'token';
}
