<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChecksTenantPublicSurfaces.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Organization;

/**
 * Mandantensperre für öffentliche, tokenbasierte Wege (Sicherheitsscan
 * 2026-08-23, S-42; Sicherheitsaudit 2026-09-17, tenant-status-1).
 *
 * Ist ein Mandant gesperrt oder abgelaufen, endet auch der Zugang über
 * Links und Tokens: Angebote annehmen, Protokolle unterschreiben, Umfragen
 * beantworten, Nachweise hochladen. Die angemeldete Oberfläche sperrt
 * {@see \App\Http\Middleware\EnforceTenantStatus} — diese Wege haben keine
 * Sitzung und liefen daran vorbei.
 *
 * Antwort ist 423 (Locked), wie an den Ingest-Endpunkten. Ohne auflösbare
 * Organisation wird nicht gesperrt: dann gibt es nichts zu entscheiden, und
 * die fachliche Prüfung des Tokens greift ohnehin.
 */
trait ChecksTenantPublicSurfaces {
    protected function assertTenantPublicSurfacesAvailable(Organization|int|null $organization): void {
        $org = $organization instanceof Organization
            ? $organization
            : (is_int($organization) && $organization > 0
                ? Organization::query()->withoutGlobalScopes()->find($organization)
                : null);

        abort_if($org instanceof Organization && ! $org->publicSurfacesAvailable(), 423);
    }
}
