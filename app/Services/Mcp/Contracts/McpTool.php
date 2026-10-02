<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : McpTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mcp\Contracts;

use App\Enums\Api\ApiAbility;

/**
 * Erweiterungspunkt des MCP-Servers (MVP-1063): jedes Modul nennt seine
 * Werkzeuge in `extensions()` seines Manifests. Umgesetzt über
 * {@see \App\Services\Mcp\GuardedTool}, das Scope, Modul und Policy prüft.
 */
interface McpTool {
    /** Token-Scope, den das Werkzeug verlangt (`mcp:read` oder `mcp:write`). */
    public function ability(): ApiAbility;
}
