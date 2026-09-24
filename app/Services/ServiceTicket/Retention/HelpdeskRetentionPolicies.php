<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\Retention;

use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/** Löschbereiche des Moduls (Aufbewahrung, Feature 130) — gemeldet über das Manifest (MVP-863). */
final class HelpdeskRetentionPolicies implements RetentionPolicyProvider {
    public function policies(): array {
        return [
            // Fehlerberichte mit Seitenkontext-PII (Vollaudit 2026-07, N15).
            new RetentionPolicy(
                area: 'problem_reports',
                modelClass: \App\Models\ServiceTicket\ProblemReport::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\ServiceTicket\ProblemReport::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('status', \App\Enums\Support\ProblemReportStatus::Closed->value)
                    ->where('updated_at', '<', $cutoff),
                purge: function (\App\Models\ServiceTicket\ProblemReport $subject): void {
                    foreach ($subject->attachments()->get() as $attachment) {
                        \Illuminate\Support\Facades\Storage::disk($attachment->disk)->delete((string) $attachment->path);
                        $attachment->delete();
                    }
                    $subject->delete();
                },
            ),
        ];
    }
}
