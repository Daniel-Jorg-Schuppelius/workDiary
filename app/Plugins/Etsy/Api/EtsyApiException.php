<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyApiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy\Api;

use App\Plugins\Support\PluginApiException;

/**
 * Etsy-API-Fehler (Feature 101): typisiert über die gemeinsame
 * {@see PluginApiException}-Basis (status/isAuthError), Fehlertexte enthalten
 * nie Tokens oder Payloads (Etsys Fehler-Schema ist `{"error": "…"}`).
 */
class EtsyApiException extends PluginApiException {}
