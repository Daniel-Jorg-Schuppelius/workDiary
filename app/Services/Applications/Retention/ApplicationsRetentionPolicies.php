<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationsRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Applications\Retention;

use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;
use App\Support\Query\DateRange;

/** Löschbereiche des Moduls (Aufbewahrung, Feature 130) — gemeldet über das Manifest (MVP-863). */
final class ApplicationsRetentionPolicies implements RetentionPolicyProvider {
    public function policies(): array {
        return [
            // Bewerbungen (Feature 068, MVP-192): purge anonymisiert
            // (Kennzahlen bleiben, PII verschwindet).
            new RetentionPolicy(
                area: 'applications',
                modelClass: \App\Models\Applications\JobApplication::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Applications\JobApplication::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->whereNull('anonymized_at')
                    ->whereNotNull('retention_until')
                    ->where('retention_until', '<', DateRange::dayAfter(now())),
                purge: function (\App\Models\Applications\JobApplication $subject): void {
                    $subject->interviews()->update(['notes' => null]);
                    $subject->reviews()->update(['comment' => null]);
                    $subject->addresses()->delete();
                    $subject->forceFill([
                        'candidate_name' => null,
                        'email' => null,
                        'phone' => null,
                        'email_hash' => null,
                        'notes' => null,
                        'status' => 'deleted',
                        'anonymized_at' => now(),
                    ])->save();
                },
            ),
        ];
    }
}
