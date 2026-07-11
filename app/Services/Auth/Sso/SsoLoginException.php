<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsoLoginException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth\Sso;

use RuntimeException;

/**
 * SSO-Fehler mit nutzerzeigbarer Meldung (Feature 057). Alles Technische
 * (Rohantworten, Claims) gehört ins Log, nie in die Message — die Message
 * landet auf der Login-Seite.
 */
class SsoLoginException extends RuntimeException {}
