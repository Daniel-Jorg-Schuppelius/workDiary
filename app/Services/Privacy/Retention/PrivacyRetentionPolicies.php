<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\Retention;

use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/** Löschbereiche des Moduls (Aufbewahrung, Feature 130) — gemeldet über das Manifest (MVP-863). */
final class PrivacyRetentionPolicies implements RetentionPolicyProvider {
    public function policies(): array {
        return [
            // Abgeschlossene Betroffenenanfragen nach Nachweisfrist.
            new RetentionPolicy(
                area: 'privacy_requests',
                modelClass: \App\Models\Privacy\DataSubjectRequest::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Privacy\DataSubjectRequest::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->whereNotNull('closed_at')
                    ->where('closed_at', '<', $cutoff),
                purge: function (\App\Models\Privacy\DataSubjectRequest $subject): void {
                    foreach ($subject->attachments()->get() as $attachment) {
                        \Illuminate\Support\Facades\Storage::disk('local')->delete((string) $attachment->path);
                        $attachment->delete();
                    }
                    $subject->delete();
                },
            ),
            // Personalstamm ausgeschiedener Mitarbeiter (Feature 130, MVP-694 —
            // H21): Kandidaten sind deaktivierte Konten mit Austrittsdatum älter
            // als die Frist (keine Portal-Konten, keine Plattform-Admins). Der
            // Vollzug ist die ANONYMISIERUNG (UserAnonymizationService) — nie
            // Löschung, die Nachweis-FKs (RETENTION_FK_TABLES) bleiben verknüpft —
            // und läuft nur als bestätigter Review-Vorschlag (approve → purge).
            new RetentionPolicy(
                area: 'employee_records',
                modelClass: \App\Models\Platform\User::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Platform\User::query()
                    ->where('organization_id', $organization->id)
                    ->whereNull('customer_id')
                    ->where('is_platform_admin', false)
                    ->whereNotNull('deactivated_at')
                    ->whereNotNull('left_at')
                    ->whereNull('anonymized_at')
                    ->whereDate('left_at', '<', $cutoff->toDateString()),
                // Personalakte (Feature 141) blockt wie Strukturdaten beim Kunden:
                // ihre Kategorie-Fristen laufen länger (bis 6 J.) und die Akte
                // braucht den Personenbezug für ihren Zweck — erst vernichten
                // (Bereich personnel_files), dann anonymisieren.
                exempt: function (\App\Models\Platform\User $subject): ?string {
                    $open = app(\App\Services\Hr\PersonnelFileService::class)->openDocumentCount($subject);

                    return $open > 0
                        ? "Personalakte mit {$open} Dokument(en) vorhanden — zuerst über den Bereich Personalakten vernichten."
                        : null;
                },
                purge: function (\App\Models\Platform\User $subject, \App\Models\Platform\User $actor): void {
                    app(\App\Services\Privacy\UserAnonymizationService::class)->anonymize($subject, $actor);
                },
            ),
        ];
    }
}
