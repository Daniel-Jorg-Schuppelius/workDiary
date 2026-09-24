<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Document\Retention;

use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;
use App\Support\MorphMap;

/** Löschbereiche des Moduls (Aufbewahrung, Feature 130) — gemeldet über das Manifest (MVP-863). */
final class DocumentRetentionPolicies implements RetentionPolicyProvider {
    public function policies(): array {
        return [
            // Eingangsrechnungen im DMS — GoBD-Ausnahme: solange nicht
            // archiviert, gilt das Dokument als in Verwendung (kein Vorschlag).
            new RetentionPolicy(
                area: 'documents_invoice',
                modelClass: \App\Models\Document\Document::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Document\Document::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('document_type', \App\Enums\Document\DocumentType::Invoice->value)
                    ->where('created_at', '<', $cutoff),
                exempt: fn($subject): ?string => $subject->getAttribute('status') !== \App\Enums\Document\DocumentStatus::Archived
                    ? 'Noch nicht archiviert — Dokument gilt als in Verwendung (GoBD).'
                    : null,
            ),
            // Digitale Personalakte (Feature 141, MVP-708): Anker ist das am
            // Dokument gesetzte Aufbewahrungsende (users.left_at + Kategorie-
            // Jahre, beim Austritt bzw. Upload für Ausgetretene gesetzt) — der
            // Katalog-Cutoff dient nur dem Ausweis. Vollzug = VERNICHTUNG
            // (Dateien + Versionen + Dokument, Audit hrFile.deleted) und nur als
            // bestätigter Review-Vorschlag (approve → purge).
            new RetentionPolicy(
                area: 'personnel_files',
                modelClass: \App\Models\Document\Document::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Document\Document::query()
                    ->withoutGlobalScopes()
                    ->whereNull('deleted_at')
                    ->where('organization_id', $organization->id)
                    ->where('documentable_type', MorphMap::alias(\App\Models\Platform\User::class))
                    ->whereNotNull('retention_until')
                    ->whereDate('retention_until', '<=', now()->toDateString()),
                purge: function (\App\Models\Document\Document $subject, \App\Models\Platform\User $actor): void {
                    app(\App\Services\Hr\PersonnelFileService::class)->destroy($subject, $actor, 'retention');
                },
            ),
        ];
    }
}
