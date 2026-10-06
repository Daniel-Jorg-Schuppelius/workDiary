<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginTenantGate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Models\Platform\Organization;
use Illuminate\Http\JsonResponse;

/**
 * Mandantensperre für Anbindungen (Entscheidung 2026-10-05): ist eine
 * Organisation gesperrt oder abgelaufen, stehen ihre geplanten Plugin-Läufe
 * und ihre Webhooks — nach dem Entsperren holt der nächste Abgleich den Stand
 * nach. Dieselbe Regel wie an den übrigen sitzungslosen Wegen
 * ({@see Organization::publicSurfacesAvailable()}).
 */
final class PluginTenantGate {
    /**
     * Bewusst ohne Merker: Queue-Worker laufen lange, eine aufgehobene Sperre
     * soll sofort gelten. Ohne auflösbare Organisation gibt es nichts zu
     * sperren; die fachliche Prüfung greift ohnehin.
     */
    public static function blocks(int $organizationId): bool {
        $organization = Organization::query()->withoutGlobalScopes()->find($organizationId);

        return $organization instanceof Organization && ! $organization->publicSurfacesAvailable();
    }

    /** Antwort eines Webhooks für einen gesperrten Mandanten — 423 wie an den Ingest-Endpunkten. */
    public static function refusal(): JsonResponse {
        return response()->json(['status' => 'tenant_blocked'], 423, ['Retry-After' => '3600']);
    }
}
