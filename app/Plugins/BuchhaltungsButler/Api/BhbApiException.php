<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BhbApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\BuchhaltungsButler\Api;

use App\Plugins\Support\PluginApiException;

/**
 * Fehler der BuchhaltungsButler-REST-API (MVP-432). Die Message trägt nur
 * Status und gekürzten Body-Auszug — nie Secret oder api_key.
 */
class BhbApiException extends PluginApiException {
    public function __construct(int $status, string $message, ?string $endpoint = null) {
        parent::__construct($message, $status, $endpoint);
    }
}
