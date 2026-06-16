<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectRateLimitException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject\Exceptions;

/**
 * Transienter Fehler: die OpenProject-API hat den Aufruf gedrosselt (HTTP 429).
 * Wird gesondert behandelt, damit das Plugin bei reiner Drosselung nicht als
 * dauerhaft fehlerhaft auto-deaktiviert wird.
 */
class OpenProjectRateLimitException extends OpenProjectApiException {}
