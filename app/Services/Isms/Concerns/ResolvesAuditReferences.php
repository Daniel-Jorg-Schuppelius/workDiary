<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesAuditReferences.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms\Concerns;

use App\Models\Isms\IsmsRequirement;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Org-sichere FK-Auflösung für die Audit-Aggregate (requirement, owner,
 * lead auditor): die org-gescopte Requirement-Query sieht fremde
 * Anforderungen nicht; User werden explizit gegen die organization_id
 * geprüft. Aus dem AuditService extrahiert (Refactoring Welle 2, B6b).
 */
trait ResolvesAuditReferences {
    /**
     * Löst die optionale Anforderungs-Referenz org-sicher auf: die
     * org-gescopte Requirement-Query (BelongsToOrganization) sieht fremde
     * Anforderungen nicht — unbekannte/fremde IDs werden abgewiesen.
     *
     * @throws ValidationException bei unbekannter/fremder Anforderung
     */
    private function resolveRequirementId(mixed $value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        $id = is_numeric($value) ? (int) $value : null;
        $requirement = $id !== null && $id > 0 ? IsmsRequirement::query()->whereKey($id)->first() : null;

        if ($requirement === null) {
            throw ValidationException::withMessages([
                'isms_requirement_id' => __('validation.exists', ['attribute' => __('isms.field.requirement')]),
            ]);
        }

        return (int) $requirement->id;
    }

    /**
     * Löst eine optionale User-Referenz org-sicher auf (User trägt kein
     * BelongsToOrganization — explizite organization_id-Prüfung).
     *
     * @throws ValidationException bei unbekanntem/fremdem User
     */
    private function resolveUserId(mixed $value, int $organizationId, string $field): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        $id = is_numeric($value) ? (int) $value : null;
        $user = $id !== null && $id > 0
            ? User::query()->whereKey($id)->where('organization_id', $organizationId)->first()
            : null;

        if ($user === null) {
            throw ValidationException::withMessages([
                $field => __('validation.exists', ['attribute' => __('isms.field.owner')]),
            ]);
        }

        return (int) $user->id;
    }
}
