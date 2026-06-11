<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Document;

use App\Enums\Document\{DocumentStatus, DocumentType};
use App\Models\{Document, DocumentVersion, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service für das Dokumentenmanagement (MVP-031).
 *
 * Versionierung ist append-only: jede Datei landet als neue
 * DocumentVersion, `current_version_id` zeigt auf die jüngste. Die
 * Versionshistorie IST die fachliche Historie — es gibt bewusst keine
 * eigene Event-Tabelle; Audit läuft über den Auditable-Trait plus
 * gezielte audit()-Events für Archivierung/Löschung.
 *
 * Dateien werden über denselben Storage-Mechanismus wie Attachments
 * abgelegt (Disk `local`, Date-Buckets, UUID-Dateinamen — siehe
 * docs/security/adr-attachment-paths.md: kein Org-Präfix, Mandanten-
 * trennung ausschließlich auf Anwendungsebene).
 */
class DocumentService {
    /**
     * Legt ein Dokument inkl. Erst-Version aus dem Upload an.
     *
     * @param  Model|null  $documentable  Customer, Project, DiaryEntry oder Asset — null = freies Dokument
     * @param  array<string, mixed>  $attributes
     */
    public function create(?Model $documentable, User $creator, array $attributes, UploadedFile $file): Document {
        $type = $this->parseType((string) ($attributes['document_type'] ?? ''));
        $status = DocumentStatus::tryFrom((string) ($attributes['status'] ?? DocumentStatus::Active->value))
            ?? DocumentStatus::Active;
        [$validFrom, $validUntil] = $this->parseValidity($attributes);

        $document = DB::transaction(function () use ($documentable, $creator, $attributes, $type, $status, $validFrom, $validUntil, $file): Document {
            $document = Document::query()->create([
                'organization_id' => $documentable?->getAttribute('organization_id') ?: $creator->organization_id,
                'documentable_type' => $documentable?->getMorphClass(),
                'documentable_id' => $documentable?->getKey(),
                'title' => $attributes['title'],
                'document_type' => $type->value,
                'status' => $status->value,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'description' => $attributes['description'] ?? null,
                'created_by_user_id' => $creator->id,
            ]);

            $this->storeVersion($document, $creator, $file, 1, $attributes['version_note'] ?? null);

            return $document->fresh(['currentVersion']) ?? $document;
        });

        // Telemetry-Light (Feature 036): aggregierter Org-Tageszähler, fire-and-forget.
        app(\App\Services\Metrics\OperationsMetricsService::class)->increment('documents.created', (int) $document->organization_id);

        return $document;
    }

    /**
     * Hängt eine neue Datei-Version an (version_no hochzählen,
     * current_version_id umsetzen).
     */
    public function addVersion(Document $document, User $actor, UploadedFile $file, ?string $note = null): DocumentVersion {
        return DB::transaction(function () use ($document, $actor, $file, $note): DocumentVersion {
            $nextNo = (int) $document->versions()->max('version_no') + 1;

            return $this->storeVersion($document, $actor, $file, $nextNo, $note);
        });
    }

    /**
     * Aktualisiert die Metadaten (Titel, Typ, Gültigkeit, Beschreibung,
     * Status draft/active). `updated`-Diff kommt automatisch über den
     * Auditable-Trait.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Document $document, User $actor, array $attributes): Document {
        $type = array_key_exists('document_type', $attributes)
            ? $this->parseType((string) $attributes['document_type'])
            : $document->document_type;
        $status = array_key_exists('status', $attributes)
            ? (DocumentStatus::tryFrom((string) $attributes['status']) ?? $document->status)
            : $document->status;
        [$validFrom, $validUntil] = $this->parseValidity($attributes + [
            'valid_from' => $attributes['valid_from'] ?? $document->valid_from?->toDateString(),
            'valid_until' => $attributes['valid_until'] ?? $document->valid_until?->toDateString(),
        ]);

        return DB::transaction(function () use ($document, $actor, $attributes, $type, $status, $validFrom, $validUntil): Document {
            unset($actor);

            $document->update([
                'title' => $attributes['title'] ?? $document->title,
                'document_type' => $type->value,
                'status' => $status->value,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'description' => array_key_exists('description', $attributes) ? $attributes['description'] : $document->description,
            ]);

            return $document;
        });
    }

    /** Archiviert manuell (Dokument bleibt mit Historie erhalten). */
    public function archive(Document $document, User $actor): Document {
        if ($document->status === DocumentStatus::Archived) {
            return $document;
        }

        return DB::transaction(function () use ($document, $actor): Document {
            $document->update(['status' => DocumentStatus::Archived->value]);
            $document->audit('document.archived', ['actor_user_id' => $actor->id]);

            return $document;
        });
    }

    /**
     * Soft-Delete. Dateien der Versionen bleiben im Storage erhalten —
     * Soft-Delete ist reversibel, endgültige Bereinigung ist Sache des
     * Datenlebenszyklus (außerhalb des MVP).
     */
    public function delete(Document $document, User $actor): void {
        DB::transaction(function () use ($document, $actor): void {
            // Fachliches Event VOR dem Delete, damit es gemeinsam mit dem
            // Auditable-`deleted` in der Hash-Kette landet.
            $document->audit('document.deleted', ['actor_user_id' => $actor->id]);
            $document->delete();
        });
    }

    /**
     * Persistiert eine Datei als Version und setzt den current-Zeiger.
     * Storage analog AttachmentController::store() (Date-Bucket + UUID).
     */
    private function storeVersion(Document $document, User $uploader, UploadedFile $file, int $versionNo, ?string $note): DocumentVersion {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $folder = 'documents/' . now()->format('Y/m');
        $filename = Str::uuid()->toString() . ($ext !== '' ? '.' . $ext : '');
        $path = $file->storeAs($folder, $filename, 'local');

        /** @var DocumentVersion $version */
        $version = $document->versions()->create([
            'version_no' => $versionNo,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $this->sanitizeFilename($file->getClientOriginalName()),
            'mime' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by_user_id' => $uploader->id,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
        ]);

        $document->forceFill(['current_version_id' => $version->id])->save();

        $document->audit('document.version.added', [
            'actor_user_id' => $uploader->id,
            'version_no' => $versionNo,
            'original_name' => $version->original_name,
        ]);

        return $version;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: ?string, 1: ?string}
     */
    private function parseValidity(array $attributes): array {
        $from = filled($attributes['valid_from'] ?? null) ? Carbon::parse((string) $attributes['valid_from'])->toDateString() : null;
        $until = filled($attributes['valid_until'] ?? null) ? Carbon::parse((string) $attributes['valid_until'])->toDateString() : null;

        if ($from !== null && $until !== null && $until < $from) {
            throw ValidationException::withMessages([
                'valid_until' => (string) __('document.error.valid_until_before_from'),
            ]);
        }

        return [$from, $until];
    }

    private function parseType(string $value): DocumentType {
        $type = DocumentType::tryFrom($value);
        if (! $type instanceof DocumentType) {
            throw ValidationException::withMessages([
                'document_type' => (string) __('document.error.unknown_type'),
            ]);
        }

        return $type;
    }

    /**
     * Bereinigt den Client-Dateinamen (analog AttachmentController):
     * keine Pfadanteile, keine Steuerzeichen, max. 255 Zeichen.
     */
    private function sanitizeFilename(string $name): string {
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '_', $name) ?? 'file';

        return mb_substr($name, 0, 255);
    }
}
