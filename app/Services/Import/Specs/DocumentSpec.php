<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Document\{DocumentStatus, DocumentType};
use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Models\{Document, Organization, User};
use App\Services\Attachments\FileAttacher;
use App\Services\Document\DocumentService;
use App\Services\Import\{ImportOutcome, ValidationIssue};
use App\Services\Import\Specs\Concerns\{ResolvesImportReferences, ValidatesImportDates};
use App\Support\Filename;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Manifest-Zeilen des Dokument-ZIP-Imports (MVP-707, Vollscan H20): `file`
 * (Pfad im Archiv), `target_type` (customer|project|asset), `target_key`
 * (Kundennummer|Projektnummer|asset_no), Titel, Dokumenttyp, Gültigkeit.
 * Kopfzeile/Normalisierung/Validierung laufen über die Spec-Pipeline; der
 * Datei-Inhalt kommt aus dem Archiv und wird über {@see persist()} durch den
 * {@see \App\Services\Import\DocumentZipImportService} übergeben — ein
 * reiner CSV-Upsert ohne Inhalt ist deshalb bewusst ein Zeilenfehler.
 * Idempotenz: (Ziel, Titel) — gleiche Datei erneut = übersprungen, andere
 * Datei = neue Version.
 */
class DocumentSpec extends AbstractEntitySpec {
    use ResolvesImportReferences;
    use ValidatesImportDates;

    public const TARGET_CUSTOMER = 'customer';

    public const TARGET_PROJECT = 'project';

    public const TARGET_ASSET = 'asset';

    /** @var list<string> */
    public const TARGET_TYPES = [self::TARGET_CUSTOMER, self::TARGET_PROJECT, self::TARGET_ASSET];

    public function __construct(private readonly DocumentService $documents) {}

    public function entity(): ImportEntity {
        return ImportEntity::Documents;
    }

    public function columns(): array {
        return [
            'file',
            'target_type',
            'target_key',
            'title',
            'document_type',
            'status',
            'valid_from',
            'valid_until',
            'description',
            'confidential',
        ];
    }

    public function requiredColumns(): array {
        return ['file', 'target_type', 'target_key', 'title'];
    }

    public function headerAliases(): array {
        return [
            'datei' => 'file',
            'dateiname' => 'file',
            'pfad' => 'file',
            'zieltyp' => 'target_type',
            'ziel' => 'target_type',
            'typ' => 'target_type',
            'zielschlüssel' => 'target_key',
            'zielnummer' => 'target_key',
            'nummer' => 'target_key',
            'kundennummer' => 'target_key',
            'titel' => 'title',
            'dokumenttyp' => 'document_type',
            'dokumentart' => 'document_type',
            'gültig ab' => 'valid_from',
            'gültig von' => 'valid_from',
            'gültig bis' => 'valid_until',
            'beschreibung' => 'description',
            'vertraulich' => 'confidential',
        ];
    }

    public function normalize(array $row): array {
        $out = [];
        foreach ($this->columns() as $col) {
            $raw = $row[$col] ?? null;
            $out[$col] = match ($col) {
                'file' => ($v = $this->trimmedString($raw)) === null ? null : ltrim(str_replace('\\', '/', $v), '/'),
                'target_type' => $this->targetType($this->trimmedString($raw)),
                'document_type', 'status' => $this->trimmedString($raw),
                'confidential' => $raw === null || trim((string) $raw) === '' ? null : $this->boolish($raw),
                default => $this->trimmedString($raw),
            };
        }

        return $out;
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];

        $file = $row['file'] ?? null;
        if ($file === null) {
            $issues[] = $this->requiredIssue('file');
        } elseif (! in_array($this->extensionOf((string) $file), DocumentService::ALLOWED_EXTENSIONS, true)) {
            $issues[] = new ValidationIssue(
                ImportErrorCode::Format,
                'file',
                (string) __('import.error.document.extension', ['ext' => $this->extensionOf((string) $file)]),
            );
        }

        if (! in_array($row['target_type'] ?? null, self::TARGET_TYPES, true)) {
            $issues[] = new ValidationIssue(ImportErrorCode::Format, 'target_type', (string) __('import.error.document.targetType'));
        } elseif (($row['target_key'] ?? null) === null) {
            $issues[] = $this->requiredIssue('target_key');
        } elseif ($this->target($organization, $row) === null) {
            $issues[] = $this->targetIssue($row);
        }

        $title = $row['title'] ?? null;
        if ($title === null) {
            $issues[] = $this->requiredIssue('title');
        } elseif (mb_strlen((string) $title) > 180) {
            $issues[] = $this->tooLongIssue('title', 180);
        }

        if (! empty($row['document_type']) && DocumentType::tryFrom((string) $row['document_type']) === null) {
            $issues[] = $this->formatIssue('document_type', (string) __('import.error.format.enum'));
        }
        if (! empty($row['status']) && ! in_array($row['status'], [DocumentStatus::Draft->value, DocumentStatus::Active->value, DocumentStatus::Archived->value], true)) {
            $issues[] = $this->formatIssue('status', (string) __('import.error.format.status'));
        }

        $this->validateDateField($issues, $row, 'valid_from');
        $this->validateDateField($issues, $row, 'valid_until');

        if (($row['description'] ?? null) !== null && mb_strlen((string) $row['description']) > 4000) {
            $issues[] = $this->tooLongIssue('description', 4000);
        }

        return $issues;
    }

    /**
     * Ohne Archiv-Inhalt gibt es nichts abzulegen — nur der ZIP-Weg ist gültig.
     */
    public function upsert(array $row, Organization $organization): array {
        return [
            ImportOutcome::Failed,
            new ValidationIssue(ImportErrorCode::Persist, 'file', (string) __('import.error.document.noContent')),
        ];
    }

    /**
     * Legt das Dokument mit Inhalt aus dem Archiv an (Erstversion) bzw. hängt
     * bei geändertem Inhalt eine neue Version an.
     *
     * @param  array<string, mixed>  $row  normalisierte, validierte Manifest-Zeile
     * @return array{0: ImportOutcome, 1: ?ValidationIssue}
     */
    public function persist(array $row, Organization $organization, User $actor, string $content): array {
        try {
            $target = $this->target($organization, $row);
            if ($target === null) {
                return [ImportOutcome::Failed, $this->targetIssue($row)];
            }

            $file = (string) $row['file'];
            $originalName = Filename::sanitize(basename($file));
            $size = strlen($content);
            $maxBytes = FileAttacher::maxKb() * 1024;
            if ($size > $maxBytes) {
                return [
                    ImportOutcome::Failed,
                    new ValidationIssue(ImportErrorCode::OutOfRange, 'file', (string) __('import.error.document.tooLarge', ['file' => $file, 'max' => FileAttacher::maxMb()])),
                ];
            }

            $mime = $this->mimeOf($content);
            if (! in_array($mime, DocumentService::ALLOWED_MIMES, true)) {
                return [
                    ImportOutcome::Failed,
                    new ValidationIssue(ImportErrorCode::Format, 'file', (string) __('import.error.document.mime', ['mime' => $mime])),
                ];
            }

            $attributes = [
                'title' => (string) $row['title'],
                'document_type' => $row['document_type'] ?? DocumentType::Other->value,
                'status' => $row['status'] ?? DocumentStatus::Active->value,
                'valid_from' => $this->dateString($row['valid_from'] ?? null),
                'valid_until' => $this->dateString($row['valid_until'] ?? null),
                'description' => $row['description'] ?? null,
                'confidential' => (bool) ($row['confidential'] ?? false),
                'version_note' => (string) __('import.legacy.position', ['number' => $file]),
            ];

            $existing = Document::query()
                ->where('organization_id', $organization->id)
                ->where('documentable_type', $target->getMorphClass())
                ->where('documentable_id', $target->getKey())
                ->where('title', $attributes['title'])
                ->with('currentVersion')
                ->first();

            if ($existing !== null) {
                $current = $existing->currentVersion;
                if ($current !== null && (int) $current->size === $size && $current->original_name === $originalName) {
                    return [ImportOutcome::Skipped, null];
                }
                $this->documents->addVersionFromContents($existing, $actor, $content, $originalName, $mime, $attributes['version_note'], 'zip-import');

                return [ImportOutcome::Updated, null];
            }

            $this->documents->createFromContents($target, $actor, $attributes, $content, $originalName, $mime);

            return [ImportOutcome::Created, null];
        } catch (Throwable $e) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage())];
        }
    }

    private function targetType(?string $value): ?string {
        $v = $value === null ? null : mb_strtolower($value);

        return match ($v) {
            'customer', 'kunde' => self::TARGET_CUSTOMER,
            'project', 'projekt' => self::TARGET_PROJECT,
            'asset' => self::TARGET_ASSET,
            default => $v,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function target(Organization $organization, array $row): ?Model {
        $key = $row['target_key'] ?? null;
        $key = $key === null ? null : (string) $key;

        return match ($row['target_type'] ?? null) {
            self::TARGET_CUSTOMER => $this->customerByNumber($organization, $key),
            self::TARGET_PROJECT => $this->projectByNumber($organization, $key),
            self::TARGET_ASSET => $this->assetByNumber($organization, $key),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function targetIssue(array $row): ValidationIssue {
        $key = match ($row['target_type'] ?? null) {
            self::TARGET_PROJECT => 'projectNumber',
            self::TARGET_ASSET => 'asset',
            default => 'customer',
        };

        return $this->fkIssue('target_key', $key, (string) ($row['target_key'] ?? ''));
    }

    private function extensionOf(string $file): string {
        return strtolower(pathinfo($file, PATHINFO_EXTENSION));
    }

    /**
     * MIME-Typ der entpackten Bytes (common-toolkit v1.28, MVP-734): finfo mit
     * deterministischem Magic-Bytes-Fallback — der app-lokale Notbehelf aus
     * MVP-707 hat ohne ext-fileinfo pauschal `application/octet-stream`
     * gemeldet und damit jede Datei am Typfilter abgewiesen.
     */
    private function mimeOf(string $content): string {
        $mime = File::mimeTypeFromContent($content);

        return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
    }
}
