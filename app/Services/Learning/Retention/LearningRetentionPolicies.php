<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning\Retention;

use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/** Löschbereiche des Moduls (Aufbewahrung, Feature 130) — gemeldet über das Manifest (MVP-863). */
final class LearningRetentionPolicies implements RetentionPolicyProvider {
    public function policies(): array {
        return [
            // Lernplattform (Feature 149, MVP-787): abgeschlossene Einschreibungen
            // ohne Zertifikat samt Versuchen, Fortschritt und Lernzeit (Kaskade).
            // Der Unterweisungsnachweis (132) lebt in einer eigenen Tabelle und
            // bleibt; eine Einschreibung MIT Zertifikat wartet auf die längere
            // Frist der Nachweise (learning_certificates).
            new RetentionPolicy(
                area: 'learning_records',
                modelClass: \App\Models\Learning\LearningEnrollment::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Learning\LearningEnrollment::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->whereIn('status', [
                        \App\Enums\Learning\LearningEnrollmentStatus::Completed->value,
                        \App\Enums\Learning\LearningEnrollmentStatus::Failed->value,
                        \App\Enums\Learning\LearningEnrollmentStatus::Expired->value,
                        \App\Enums\Learning\LearningEnrollmentStatus::Cancelled->value,
                    ])
                    ->where('updated_at', '<', $cutoff),
                exempt: fn(\App\Models\Learning\LearningEnrollment $enrollment): ?string => \App\Models\Learning\LearningCertificate::query()
                    ->withoutGlobalScopes()->where('learning_enrollment_id', $enrollment->id)->exists()
                        ? (string) __('Zertifikat vorhanden — Nachweisfrist gilt')
                        : null,
                purge: function (\App\Models\Learning\LearningEnrollment $subject): void {
                    $subject->delete();
                },
            ),
            // Zertifikate: der Nachweis bleibt prüfbar (Nummer, Code, Gültigkeit),
            // die Person wird auf Initialen gekürzt — die Prüfseite antwortet
            // weiter „gültig/widerrufen“, ohne den Namen preiszugeben.
            new RetentionPolicy(
                area: 'learning_certificates',
                modelClass: \App\Models\Learning\LearningCertificate::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Learning\LearningCertificate::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('issued_on', '<', $cutoff)
                    ->where(fn($q) => $q->whereNull('valid_until')->orWhere('valid_until', '<', $cutoff))
                    ->where('holder_name', 'not like', '%.')
                    ->whereNotNull('holder_name'),
                purge: function (\App\Models\Learning\LearningCertificate $subject): void {
                    $initials = implode(' ', array_map(
                        static fn (string $part): string => mb_substr($part, 0, 1) . '.',
                        array_filter(preg_split('/\s+/u', trim((string) $subject->holder_name)) ?: []),
                    ));
                    $subject->forceFill(['holder_name' => $initials !== '' ? $initials : '–'])->save();
                },
            ),
        ];
    }
}
