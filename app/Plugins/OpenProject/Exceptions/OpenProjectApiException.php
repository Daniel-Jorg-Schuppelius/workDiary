<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject\Exceptions;

use App\Plugins\Support\PluginApiException;

/**
 * Harter Fehler der OpenProject-API (Auth, 4xx/5xx, ungültige Antwort).
 * Signalisiert dem Aufrufer einen nicht-transienten Zustand.
 */
class OpenProjectApiException extends PluginApiException {}
