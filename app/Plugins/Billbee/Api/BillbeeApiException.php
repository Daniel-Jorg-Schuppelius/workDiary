<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Billbee\Api;

use App\Plugins\Support\PluginApiException;

/**
 * Fehler der Billbee-REST-API (MVP-433). Die Message trägt nur Status und
 * gekürzten Body-Auszug — nie API-Key oder API-Passwort.
 */
class BillbeeApiException extends PluginApiException {}
