<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Api;

use App\Plugins\Support\PluginApiException;

/**
 * Fehler der easybill-REST-API (MVP-431). Die Message trägt nur Status und
 * gekürzten Body-Auszug — nie den API-Key.
 */
class EasybillApiException extends PluginApiException {
    public function __construct(int $status, string $message, ?string $endpoint = null) {
        parent::__construct($message, $status, $endpoint);
    }
}
