<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionRegistrations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\Retention;

use App\Models\{CommunicationNote, TimeExport};

/**
 * Registriert alle {@see RetentionPolicy}-Einträge der App in der
 * {@see RetentionRegistry} (B4 aus dem AppServiceProvider gezogen).
 * Aufruf einmalig beim Container-Aufbau der Registry.
 */
class RetentionRegistrations {
    public static function register(RetentionRegistry $registry): void {
        // CTI-Anrufmetadaten (Vollaudit 2026-07, M18): Rufnummer aus
        // Referenz-Payload und Notiz-Betreff anonymisieren; Richtung/
        // Zeitpunkt/Dauer bleiben als Vorgangsnachweis.
        $registry->register(new RetentionPolicy(
            area: 'cti_calls',
            modelClass: \App\Models\ExternalReference::class,
            overdueQuery: fn($organization, $cutoff) => \App\Models\ExternalReference::query()
                ->forPlugin($organization->id, \App\Services\Cti\CtiCallService::PLUGIN_ID, \App\Services\Cti\CtiCallService::EXTERNAL_TYPE)
                ->where('synced_at', '<', $cutoff)
                ->whereRaw("json_extract(payload, '$.anonymized') is null"),
            purge: function (\App\Models\ExternalReference $subject): void {
                $payload = (array) $subject->payload;
                unset($payload['number']);
                $subject->forceFill(['payload' => [...$payload, 'anonymized' => true]])->save();
                $note = $subject->referenceable;
                if ($note instanceof CommunicationNote) {
                    $note->forceFill(['subject' => (string) __('Anruf (anonymisiert)')])->save();
                }
            },
        ));

        // Ideenkarten im Papierkorb (Vollaudit 2026-07, M21): soft-gelöschte
        // Karten nach Frist endgültig entfernen (Knoten/Links/Shares kaskadieren).
        $registry->register(new RetentionPolicy(
            area: 'idea_maps',
            modelClass: \App\Models\IdeaMap::class,
            overdueQuery: fn($organization, $cutoff) => \App\Models\IdeaMap::query()
                ->withoutGlobalScopes()
                ->onlyTrashed()
                ->where('organization_id', $organization->id)
                ->where('deleted_at', '<', $cutoff),
            purge: function (\App\Models\IdeaMap $subject): void {
                $subject->forceDelete();
            },
        ));

        // Fehlerberichte mit Seitenkontext-PII (Vollaudit 2026-07, N15).
        $registry->register(new RetentionPolicy(
            area: 'problem_reports',
            modelClass: \App\Models\ProblemReport::class,
            overdueQuery: fn($organization, $cutoff) => \App\Models\ProblemReport::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('status', \App\Enums\Support\ProblemReportStatus::Closed->value)
                ->where('updated_at', '<', $cutoff),
            purge: function (\App\Models\ProblemReport $subject): void {
                foreach ($subject->attachments()->get() as $attachment) {
                    \Illuminate\Support\Facades\Storage::disk($attachment->disk)->delete((string) $attachment->path);
                    $attachment->delete();
                }
                $subject->delete();
            },
        ));

        // Führerscheinkontrollen (Vollaudit 2026-07, N24): nach Nachweisfrist
        // löschen — Vorschlag über den Review-Scan, keine Direktlöschung.
        $registry->register(new RetentionPolicy(
            area: 'driver_license_checks',
            modelClass: \App\Models\DriverLicenseCheck::class,
            overdueQuery: fn($organization, $cutoff) => \App\Models\DriverLicenseCheck::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('checked_at', '<', $cutoff),
            purge: function (\App\Models\DriverLicenseCheck $subject): void {
                $subject->delete();
            },
        ));

        // Abgeschlossene Betroffenenanfragen nach Nachweisfrist.
        $registry->register(new RetentionPolicy(
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
        ));

        // Bewerbungen (Feature 068, MVP-192): purge anonymisiert
        // (Kennzahlen bleiben, PII verschwindet).
        $registry->register(new RetentionPolicy(
            area: 'applications',
            modelClass: \App\Models\Applications\JobApplication::class,
            overdueQuery: fn($organization, $cutoff) => \App\Models\Applications\JobApplication::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereNull('anonymized_at')
                ->whereNotNull('retention_until')
                ->where('retention_until', '<=', now()->toDateString()),
            purge: function (\App\Models\Applications\JobApplication $subject): void {
                $subject->interviews()->update(['notes' => null]);
                $subject->reviews()->update(['comment' => null]);
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
        ));

        // Leads (Feature 091, MVP-656): personenbezogene Daten ohne
        // Vertrag - nicht konvertierte Leads werden 6 Monate nach dem
        // letzten Kontakt anonymisiert (PII weg, Pipeline-Kennzahl bleibt).
        $registry->register(new RetentionPolicy(
            area: 'leads',
            modelClass: \App\Models\Lead::class,
            overdueQuery: fn($organization, $cutoff) => \App\Models\Lead::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereNull('anonymized_at')
                ->where('status', '!=', \App\Enums\Sales\LeadStatus::Converted->value)
                ->whereNotNull('last_contact_at')
                ->where('last_contact_at', '<=', now()->subMonths((int) config('sales.lead_retention_months', 6))),
            purge: function (\App\Models\Lead $subject): void {
                app(\App\Services\Sales\LeadService::class)->anonymize($subject);
            },
        ));

        // Reklamationsakten (Feature 072, MVP-256): abgeschlossene Fälle
        // nach Ablauf anonymisieren (Melder-PII), Kennzahlen bleiben.
        $registry->register(new RetentionPolicy(
            area: 'claims',
            modelClass: \App\Models\Claims\ClaimCase::class,
            overdueQuery: fn($organization, $cutoff) => \App\Models\Claims\ClaimCase::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereNull('anonymized_at')
                ->whereNotNull('closed_at')
                ->where('closed_at', '<', $cutoff),
            purge: function (\App\Models\Claims\ClaimCase $subject): void {
                $subject->forceFill([
                    'reporter_name' => null,
                    'reporter_email' => null,
                    'anonymized_at' => now(),
                ])->save();
            },
        ));

        // Lohn-/Zeitexporte inkl. abgelegter Dateien. Vollaudit 2026-07
        // (N6): Purge auditiert jetzt als export.deleted und räumt Zeilen mit.
        $registry->register(new RetentionPolicy(
            area: 'exports',
            modelClass: TimeExport::class,
            overdueQuery: fn($organization, $cutoff) => TimeExport::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('created_at', '<', $cutoff),
            purge: function (TimeExport $subject): void {
                $subject->audit('export.deleted', ['reason' => 'retention', 'file_path' => $subject->file_path]);
                $path = (string) ($subject->file_path ?? '');
                if ($path !== '') {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
                }
                $subject->lines()->delete();
                $subject->delete();
            },
        ));

        // Eingangsrechnungen im DMS — GoBD-Ausnahme: solange nicht
        // archiviert, gilt das Dokument als in Verwendung (kein Vorschlag).
        $registry->register(new RetentionPolicy(
            area: 'documents_invoice',
            modelClass: \App\Models\Document::class,
            overdueQuery: fn($organization, $cutoff) => \App\Models\Document::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('document_type', \App\Enums\Document\DocumentType::Invoice->value)
                ->where('created_at', '<', $cutoff),
            exempt: fn($subject): ?string => $subject->getAttribute('status') !== \App\Enums\Document\DocumentStatus::Archived
                ? 'Noch nicht archiviert — Dokument gilt als in Verwendung (GoBD).'
                : null,
        ));

        // Fahrtakten (MVP-456, Konzept §11): abgeschlossene Fahrten werden
        // nach Frist anonymisiert — Orts-/Fahrgastfelder genullt (encrypted-
        // Regel: NULL, nie ""), Beträge/Steuer/Zeiten bleiben als Nachweis.
        $registry->register(new RetentionPolicy(
            area: 'passenger_rides',
            modelClass: \App\Models\Passenger\PassengerRide::class,
            overdueQuery: fn($organization, $cutoff) => \App\Models\Passenger\PassengerRide::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereIn('status', array_values(array_map(
                    static fn(\App\Enums\Passenger\RideStatus $status): string => $status->value,
                    array_filter(\App\Enums\Passenger\RideStatus::cases(), static fn(\App\Enums\Passenger\RideStatus $status): bool => $status->isFinal()),
                )))
                ->whereNull('anonymized_at')
                ->where(fn($query) => $query
                    ->where('completed_at', '<', $cutoff)
                    ->orWhere('cancelled_at', '<', $cutoff)),
            purge: function (\App\Models\Passenger\PassengerRide $subject): void {
                $subject->forceFill([
                    'pickup_address' => null,
                    'destination_address' => null,
                    'waypoints' => null,
                    'passenger_name' => null,
                    'passenger_contact' => null,
                    'route_note' => null,
                    'closing_note' => null,
                    'anonymized_at' => now(),
                ])->save();
                $subject->audit('passenger.ride_anonymized', ['reason' => 'retention']);
            },
        ));
    }
}
