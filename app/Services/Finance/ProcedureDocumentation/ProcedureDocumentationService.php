<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDocumentationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ProcedureDocumentation;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Finance\ProcedureDocumentationStatus;
use App\Models\Finance\ProcedureDocumentation;
use App\Models\{Organization, User};
use App\Services\Concerns\{AssertsStatusTransition, AssignsSequentialNo};
use App\Services\DocumentDesign\DocumentDesignRenderer;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Lebenszyklus der Verfahrensdokumentation (Feature 134, MVP-699): ein
 * Entwurf je Org (Freitexte aus der letzten Veröffentlichung vorbelegt),
 * Veröffentlichen = Ketten nachrechnen, Systemteil als Snapshot einfrieren,
 * PDF erzeugen und beide per SHA-256 belegen. Das PDF liegt auf der privaten
 * Disk; der Download prüft den Hash gegen die Datei (Nachweis).
 */
final class ProcedureDocumentationService {
    use AssertsStatusTransition;
    use AssignsSequentialNo;

    public const STORAGE_PREFIX = 'procedure-documentation/';

    public function __construct(
        private readonly ProcedureDocumentationBuilder $builder,
        private readonly DocumentDesignRenderer $renderer,
    ) {}

    /**
     * Neuer Entwurf mit laufender Version; Freitexte aus der letzten
     * veröffentlichten Version vorbelegt (Attribute überschreiben).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createDraft(Organization $organization, ?User $actor, array $attributes = []): ProcedureDocumentation {
        $exists = ProcedureDocumentation::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', ProcedureDocumentationStatus::Draft->value)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['status' => (string) __('procedure-documentation.error.draft_exists')]);
        }

        /** @var ProcedureDocumentation|null $latest */
        $latest = ProcedureDocumentation::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', ProcedureDocumentationStatus::Published->value)
            ->orderByDesc('version')
            ->first();

        return DB::transaction(function () use ($organization, $actor, $attributes, $latest): ProcedureDocumentation {
            $texts = [];
            foreach (ProcedureDocumentation::TEXT_FIELDS as $field) {
                $texts[$field] = array_key_exists($field, $attributes) ? $attributes[$field] : $latest?->{$field};
            }

            return ProcedureDocumentation::query()->create($texts + [
                'organization_id' => $organization->id,
                'version' => $this->nextNo(ProcedureDocumentation::class, 'version', 'organization_id', (int) $organization->id),
                'status' => ProcedureDocumentationStatus::Draft->value,
                'created_by_user_id' => $actor?->id,
            ]);
        });
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(ProcedureDocumentation $document, array $attributes): ProcedureDocumentation {
        if (! $document->isEditable()) {
            throw ValidationException::withMessages(['status' => (string) __('procedure-documentation.error.frozen')]);
        }

        $changes = [];
        foreach (ProcedureDocumentation::TEXT_FIELDS as $field) {
            if (array_key_exists($field, $attributes)) {
                $changes[$field] = $attributes[$field];
            }
        }
        $document->update($changes);

        return $document->refresh();
    }

    /**
     * Live-Vorschau des generierten Teils (ohne Ketten-Nachrechnung).
     *
     * @return array<string, mixed>
     */
    public function preview(Organization $organization): array {
        return $this->builder->build($organization, false);
    }

    /**
     * Veröffentlichen: Ketten vollständig nachrechnen, Snapshot + PDF
     * einfrieren, Hashes speichern. Danach greift der Unveränderlichkeits-
     * Guard des Modells.
     */
    public function publish(ProcedureDocumentation $document, ?User $actor): ProcedureDocumentation {
        $this->assertStatusTransition($document->status, ProcedureDocumentationStatus::Published);

        /** @var Organization $organization */
        $organization = $document->organization()->withoutGlobalScopes()->firstOrFail();
        $payload = $this->builder->build($organization, true);
        $json = $this->builder->toJson($payload);
        $publishedAt = Carbon::now();

        $pdf = $this->renderPdf($document, $payload, $publishedAt);
        $path = self::STORAGE_PREFIX . $organization->id . '/verfahrensdokumentation-v' . $document->version . '.pdf';
        Storage::disk('local')->put($path, $pdf);

        $snapshotHash = (string) CryptoHelper::hash($json);
        $pdfHash = (string) CryptoHelper::hash($pdf);

        DB::transaction(function () use ($document, $payload, $snapshotHash, $pdfHash, $path, $publishedAt, $actor): void {
            $document->forceFill([
                'status' => ProcedureDocumentationStatus::Published->value,
                'snapshot' => $payload,
                'snapshot_sha256' => $snapshotHash,
                'pdf_path' => $path,
                'pdf_sha256' => $pdfHash,
                'published_at' => $publishedAt,
                'published_by' => $actor?->id,
            ])->save();
            $document->audit('procedure_documentation.published', [
                'version' => $document->version,
                'snapshot_sha256' => $snapshotHash,
                'pdf_sha256' => $pdfHash,
            ]);
        });

        return $document->refresh();
    }

    /**
     * Gespeichertes PDF der veröffentlichten Version — nur, wenn der Hash
     * noch zur Datei passt (sonst ist der Nachweis verletzt).
     */
    public function pdfBytes(ProcedureDocumentation $document): string {
        if (! $document->isPublished() || $document->pdf_path === null) {
            throw new RuntimeException((string) __('procedure-documentation.error.not_published'));
        }
        $disk = Storage::disk('local');
        if (! $disk->exists($document->pdf_path)) {
            throw new RuntimeException((string) __('procedure-documentation.error.pdf_missing'));
        }
        $bytes = (string) $disk->get($document->pdf_path);
        if (! hash_equals((string) $document->pdf_sha256, (string) CryptoHelper::hash($bytes))) {
            throw new RuntimeException((string) __('procedure-documentation.error.pdf_mismatch'));
        }

        return $bytes;
    }

    /** @param array<string, mixed> $payload */
    private function renderPdf(ProcedureDocumentation $document, array $payload, Carbon $publishedAt): string {
        return $this->renderer->renderPdf(
            RenderDocumentKind::Report,
            'finance.procedure-documentation.document-pdf',
            ['document' => $document, 'payload' => $payload, 'publishedAt' => $publishedAt],
            (int) $document->organization_id,
        );
    }
}
