<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxScopePreflight.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use App\Models\OrgaMaxConnection;

/**
 * Scope-Preflight (Feature 077, MVP-306): je Capability die in orgaMAX
 * freizuschaltenden Scopes. Fehlende Scopes erzeugen einen Blocked-State mit
 * Anleitung — das Plugin wird nie teilweise schreibend aktiviert.
 */
class OrgaMaxScopePreflight {
    /** @var array<string, list<string>> Capability → benötigte Scopes. */
    private const REQUIRED = [
        'customers' => ['customer:read'],
        'suppliers' => ['supplier:read'],
        'articles' => ['article:read'],
        'billing' => ['order:read', 'order:write', 'invoice:read'],
        'payments' => ['invoice:read'],
        'expenses' => ['expense:read', 'expense:write'],
        'documents' => ['file:read'],
    ];

    /** @return list<string> Scopes, die für die aktivierten Capabilities fehlen. */
    public function missing(OrgaMaxConnection $connection): array {
        $granted = array_map('strval', (array) $connection->granted_scopes);
        $missing = [];

        foreach (self::REQUIRED as $capability => $scopes) {
            if (! $connection->capabilityEnabled($capability)) {
                continue;
            }
            foreach ($scopes as $scope) {
                if (! in_array($scope, $granted, true)) {
                    $missing[] = $scope;
                }
            }
        }

        return array_values(array_unique($missing));
    }

    /** @return array<string, list<string>> Für die Admin-Anzeige (Capability-Matrix). */
    public static function requiredScopes(): array {
        return self::REQUIRED;
    }
}
