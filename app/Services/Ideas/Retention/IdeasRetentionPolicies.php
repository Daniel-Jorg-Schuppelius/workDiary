<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeasRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ideas\Retention;

use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/** Löschbereiche des Moduls (Aufbewahrung, Feature 130) — gemeldet über das Manifest (MVP-863). */
final class IdeasRetentionPolicies implements RetentionPolicyProvider {
    public function policies(): array {
        return [
            // Ideenkarten im Papierkorb (Vollaudit 2026-07, M21): soft-gelöschte
            // Karten nach Frist endgültig entfernen (Knoten/Links/Shares kaskadieren).
            new RetentionPolicy(
                area: 'idea_maps',
                modelClass: \App\Models\Ideas\IdeaMap::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Ideas\IdeaMap::query()
                    ->withoutGlobalScopes()
                    ->onlyTrashed()
                    ->where('organization_id', $organization->id)
                    ->where('deleted_at', '<', $cutoff),
                purge: function (\App\Models\Ideas\IdeaMap $subject): void {
                    $subject->forceDelete();
                    // Verweise der Knoten haben keinen Fremdschlüssel mehr (MVP-811).
                    \App\Models\Knowledge\ContentReference::pruneOrphans((int) $subject->organization_id);
                },
            ),
        ];
    }
}
