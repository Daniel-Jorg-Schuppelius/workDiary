<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubjectDataExporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Privacy\DataSubjectKind;
use App\Models\Applications\JobApplication;
use App\Models\{Customer, Lead, Supplier, User};
use App\Models\Privacy\{DataSubjectRequest, PrivacyAttachment};
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Services\Privacy\SubjectData\{
    ApplicationRecordsSection,
    AuditTrailSection,
    CommunicationNotesSection,
    ContactDetailsSection,
    CustomerDocumentsSection,
    CustomerMasterDataSection,
    JobApplicationMasterDataSection,
    LeadMasterDataSection,
    LocationPointsSection,
    PersonnelFileSection,
    PortalUserMasterDataSection,
    SubjectDataSection,
    SupplierDocumentsSection,
    SupplierMasterDataSection,
    UserMasterDataSection,
    WorkTimeSummarySection
};
use App\Support\Toolkit\CsvFacade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Auskunftsgenerator je Betroffenenart (Feature 129, Vollscan H2): baut aus
 * den {@see SubjectDataSection}-Abschnitten die echte Betroffenen-Auskunft
 * (Art. 15) als JSON + PDF und die Datenübertragbarkeits-CSV (Art. 20, nur
 * Stammdaten) und legt die Dateien mit dem Fall-DEK verschlüsselt am
 * Betroffenenfall ab — Crypto-Shredding des Falls vernichtet damit auch die
 * erzeugten Auskunftspakete.
 */
class SubjectDataExporter {
    /** Ablage-Präfix der DEK-verschlüsselten Exportdateien (private Disk). */
    public const STORAGE_PREFIX = 'privacy/exports/';

    public function __construct(private readonly PrivacyEventService $events) {}

    /**
     * Abschnitts-Verdrahtung je Betroffenenart (Muster GoBD-Sections).
     *
     * @return list<SubjectDataSection>
     */
    public function sectionsFor(DataSubjectKind $kind): array {
        return match ($kind) {
            DataSubjectKind::User => [
                new UserMasterDataSection,
                new WorkTimeSummarySection,
                new LocationPointsSection,
                new PersonnelFileSection,
                new AuditTrailSection,
            ],
            DataSubjectKind::PortalUser => [
                new PortalUserMasterDataSection,
                new AuditTrailSection,
            ],
            DataSubjectKind::Customer => [
                new CustomerMasterDataSection,
                new ContactDetailsSection,
                new CommunicationNotesSection,
                new CustomerDocumentsSection,
            ],
            DataSubjectKind::Supplier => [
                new SupplierMasterDataSection,
                new ContactDetailsSection,
                new SupplierDocumentsSection,
            ],
            DataSubjectKind::Lead => [
                new LeadMasterDataSection,
                new CommunicationNotesSection,
            ],
            DataSubjectKind::JobApplication => [
                new JobApplicationMasterDataSection,
                new ApplicationRecordsSection,
            ],
        };
    }

    /**
     * Betroffenen-Datensatz hart org-gescopt auflösen — fremde Organisationen
     * enden im ModelNotFound (404), nie in einer leeren Auskunft.
     */
    public function resolve(DataSubjectKind $kind, int $organizationId, int $id): Model {
        $class = $kind->modelClass();
        $query = $class::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereKey($id);
        if ($kind === DataSubjectKind::PortalUser) {
            $query->whereNotNull('customer_id');
        }

        return $query->firstOrFail();
    }

    /**
     * Vollständige Auskunfts-Datenstruktur (JSON-/PDF-/CSV-Quelle).
     *
     * @return array<string, mixed>
     */
    public function build(DataSubjectRequest $request, DataSubjectKind $kind, Model $subject): array {
        $organization = $request->organization;
        $sections = [];
        foreach ($this->sectionsFor($kind) as $section) {
            $sections[] = array_merge([
                'key' => $section->key(),
                'title' => $section->title(),
                'portable' => $section->portable(),
            ], $section->build($subject));
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'organization' => $organization !== null ? (string) $organization->name : '',
            'request_number' => (string) $request->request_number,
            'subject_kind' => $kind->value,
            'subject_kind_label' => $kind->label(),
            'subject_id' => (string) $subject->getAttribute('sqid'),
            'subject_label' => $this->subjectLabel($kind, $subject),
            'sections' => $sections,
        ];
    }

    /** Anzeigename des Betroffenen (nur für Kopf der Auskunft, nie für Events). */
    public function subjectLabel(DataSubjectKind $kind, Model $subject): string {
        $label = match ($kind) {
            DataSubjectKind::User, DataSubjectKind::PortalUser => $subject instanceof User ? $subject->name : null,
            DataSubjectKind::Customer => $subject instanceof Customer ? ($subject->name ?: $subject->company) : null,
            DataSubjectKind::Supplier => $subject instanceof Supplier ? ($subject->name ?: $subject->company) : null,
            DataSubjectKind::Lead => $subject instanceof Lead ? $subject->displayName() : null,
            DataSubjectKind::JobApplication => $subject instanceof JobApplication ? $subject->candidate_name : null,
        };

        return trim((string) $label) !== '' ? (string) $label : '—';
    }

    /** @param array<string, mixed> $payload */
    public function toJson(array $payload): string {
        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Art.-20-CSV: NUR die vom Betroffenen bereitgestellten Stammdaten
     * (portable Abschnitte), flach als eine Zeile mit Maschinenschlüsseln.
     *
     * @param array<string, mixed> $payload
     */
    public function toPortabilityCsv(array $payload): string {
        $row = [];
        /** @var list<array<string, mixed>> $sections */
        $sections = $payload['sections'] ?? [];
        foreach ($sections as $section) {
            if (empty($section['portable'])) {
                continue;
            }
            /** @var array<string, array{label: string, value: string|null}> $fields */
            $fields = $section['fields'] ?? [];
            foreach ($fields as $key => $field) {
                // Schlüsselkollision zwischen Abschnitten: Abschnittspräfix.
                $column = array_key_exists($key, $row) ? ($section['key'] ?? 'section') . '_' . $key : $key;
                $row[$column] = $field['value'] ?? '';
            }
        }

        return CsvFacade::buildCsv(array_keys($row), [$row]);
    }

    /**
     * Lesbare Art.-15-Auskunft als PDF (übers pdf-toolkit, Muster DSFA-Bericht).
     *
     * @param array<string, mixed> $payload
     */
    public function renderPdf(array $payload, ?int $organizationId): string {
        return app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Report,
            'privacy.requests.subject-export-pdf',
            ['payload' => $payload],
            $organizationId,
        );
    }

    /**
     * Erzeugt JSON/PDF/CSV, verschlüsselt sie mit dem Fall-DEK und hängt sie
     * als Anhänge an den Fall. Ereignis in der Hash-Kette OHNE Klartext-PII.
     * Für Mitarbeiter (Feature 141) kommen die aktuellen Dateien der
     * Personalakte als weitere DEK-verschlüsselte Anhänge dazu.
     *
     * @param array<string, mixed> $payload
     * @return list<PrivacyAttachment>
     */
    public function attachFiles(DataSubjectRequest $request, DataSubjectKind $kind, array $payload, ?User $actor, ?Model $subject = null): array {
        $dek = $request->recordDek();
        if ($dek === null) {
            throw new RuntimeException('Fall ist kryptografisch geschreddert – Auskunft kann nicht abgelegt werden.');
        }

        $number = (string) $request->request_number;
        $files = [
            ['auskunft-' . $number . '.json', 'application/json', $this->toJson($payload)],
            ['auskunft-' . $number . '.pdf', 'application/pdf', $this->renderPdf($payload, (int) $request->organization_id)],
            ['datenuebertragbarkeit-' . $number . '.csv', 'text/csv', $this->toPortabilityCsv($payload)],
        ];
        if ($kind === DataSubjectKind::User && $subject instanceof User) {
            array_push($files, ...$this->personnelFileContents($subject, $number));
        }

        $crypto = app(DataProtectionCryptoService::class);
        $attachments = [];
        foreach ($files as [$filename, $mime, $bytes]) {
            $path = self::STORAGE_PREFIX . $request->id . '/' . Str::random(40) . '.enc';
            Storage::disk('local')->put($path, $crypto->encryptWithDek($bytes, $dek));

            $attachments[] = PrivacyAttachment::create([
                'organization_id' => $request->organization_id,
                'attachable_type' => $request->getMorphClass(),
                'attachable_id' => $request->getKey(),
                'filename' => $filename,
                'path' => $path,
                'size' => strlen($bytes),
                'mime' => $mime,
                'uploaded_by' => $actor?->id,
            ]);
        }

        $this->events->record($request, 'subject_export_generated', $actor, [
            'kind' => $kind->value,
            'files' => count($attachments),
        ]);

        return $attachments;
    }

    /**
     * Aktuelle Version jedes Personalakten-Dokuments als Rohbytes
     * (Feature 141 × Art. 15 Abs. 3 — Kopie der Daten).
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function personnelFileContents(User $member, string $number): array {
        $files = [];
        $documents = \App\Models\Document::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->personnelFilesOf($member)
            ->with('currentVersion')
            ->orderBy('id')
            ->get();
        foreach ($documents as $index => $document) {
            $version = $document->currentVersion;
            if ($version === null) {
                continue;
            }
            $disk = Storage::disk($version->disk);
            if (! $disk->exists($version->path)) {
                continue;
            }
            $files[] = [
                sprintf('personalakte-%s-%02d-%s', $number, $index + 1, $version->original_name),
                (string) ($version->mime ?: 'application/octet-stream'),
                (string) $disk->get($version->path),
            ];
        }

        return $files;
    }
}
