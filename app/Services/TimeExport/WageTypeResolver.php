<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WageTypeResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeExport;

use App\Models\Scopes\OrganizationScope;
use App\Models\{TimeExportLine, WageTypeMapping};

/**
 * Löst die externe Lohnartennummer einer Exportzeile auf (A21 · MVP-019).
 *
 * Reihenfolge:
 *   1. Organisations-Mapping {@see WageTypeMapping} für (Profil, wage_type)
 *      — die explizite Konfiguration je Zielsystem gewinnt.
 *   2. wage_type_code der Zeile (aus der Zuschlagsregel, Feature 005).
 *   3. null — das Profil wendet dann seinen bisherigen Default an
 *      (`normal_wage_type_code` für work.normal, Rückwärtskompatibilität).
 *
 * Lädt die Mappings der Organisation einmalig (ein Query pro Export) und
 * arbeitet bewusst ohne OrganizationScope, damit Queue-/CLI-Kontexte ohne
 * gebundene currentOrganization identisch auflösen.
 */
class WageTypeResolver {
    /** @var array<string, string> wage_type → external_code */
    private array $map;

    public function __construct(int $organizationId, string $profileKey) {
        /** @var array<string, string> $map */
        $map = WageTypeMapping::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('profile', $profileKey)
            ->pluck('external_code', 'wage_type')
            ->all();

        $this->map = $map;
    }

    /** Externe Lohnart aus dem Org-Mapping, null wenn nicht gepflegt. */
    public function map(string $wageType): ?string {
        $code = $this->map[$wageType] ?? null;

        return $code !== null && $code !== '' ? $code : null;
    }

    /**
     * Externe Lohnart der Zeile: Mapping vor Zeilen-Code (Zuschlagsregel);
     * null, wenn beides fehlt — der Profil-Default bleibt Sache des Profils.
     */
    public function resolveCode(TimeExportLine $line): ?string {
        $mapped = $this->map((string) $line->wage_type);
        if ($mapped !== null) {
            return $mapped;
        }

        $lineCode = $line->wage_type_code;

        return is_string($lineCode) && $lineCode !== '' ? $lineCode : null;
    }
}
