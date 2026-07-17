<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Api;

use App\Plugins\Support\PluginApiException;

/**
 * Fehler der orgaMAX-OpenAPI (Feature 077). Die Message trägt nur Status und
 * gekürzten Body-Auszug — nie API-Key, Secret, ownershipId oder Token.
 */
class OrgaMaxApiException extends PluginApiException {
    public function __construct(int $status, string $message, ?string $endpoint = null) {
        parent::__construct($message, $status, $endpoint);
    }
}
