<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintOrderService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Print;

use App\Enums\AssetCompliance\AssetComplianceStatus;
use App\Enums\Print\{PreflightStatus, PrintOrderStatus, PrintOutputKind};
use App\Enums\Print\PrintQcStatus;
use App\Models\Asset\Asset;
use App\Models\Attachments\Attachment;
use App\Models\Document\{Document, DocumentVersion};
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Platform\{Organization, User};
use App\Models\Print\PrintOrder;
use App\Models\Shipping\Shipment;
use App\Services\Asset\AssetUsageGuard;
use App\Services\Asset\Contracts\AssetComplianceStatusProvider;
use App\Services\Concerns\AssertsValidatedTransition;
use App\Services\Document\DocumentService;
use App\Services\Print\Preflight\{BasicPreflightProvider, PreflightProvider, PreflightReport};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

/**
 * Druckauftrags-Lebenszyklus (MVP-459): Datenannahme → Preflight →
 * Druckfreigabe → Produktion → Qualitätskontrolle → Ausgabe/Versand.
 *
 * Grundsätze (Issue #75):
 *  - Genau EIN Fertigungsauftrag je Druckauftrag; Mengen/Material/Lager/
 *    Nachkalkulation bleiben in der Fertigung (kein Parallelmodell).
 *  - Die Freigabe bindet Person, Zeitpunkt, Datei-Hash und Produktions-
 *    Snapshot unveränderlich; eine neue Dateiversion setzt den Auftrag
 *    zurück auf prüf-/freigabepflichtig.
 *  - Blockierende Preflight-Fehler verhindern die Freigabe; ein manueller
 *    Override ist nur begründet und auditiert möglich.
 *  - Maschinen (Assets) mit Sperre, überfälliger Pflichtprüfung oder
 *    erforderlicher Kalibrierung können nicht regulär starten (D12-Guard).
 *  - Löschfristen entfernen nur die Produktionsdatei, nie den
 *    kaufmännischen Nachweis (Auftrag, Snapshot, Hash bleiben).
 */
class PrintOrderService {
    use AssertsValidatedTransition;
    /** Branchenprofil-Code (Seed unter database/data/branchprofiles). */
    public const PROFILE_CODE = 'druck-kopiershop';

    /** Guard-Kontext für die Maschinen-Einsatzprüfung. */
    public const ASSET_CONTEXT = 'print_production';

    /** Parameter, die der Kunde freigibt und die interne Freigabe übernehmen muss (MVP-1076). */
    public const CUSTOMER_PARAMETERS = ['final_format', 'quantity', 'color_mode', 'material'];

    public function __construct(
        private readonly AssetUsageGuard $assetGuard,
        private readonly AssetComplianceStatusProvider $compliance,
        private readonly DocumentService $documents,
    ) {}

    /**
     * Druckauftrag zum bestehenden Fertigungsauftrag eröffnen (1:1).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function open(ManufacturingOrder $manufacturingOrder, User $actor, array $attributes = []): PrintOrder {
        if (PrintOrder::query()->where('manufacturing_order_id', $manufacturingOrder->id)->exists()) {
            throw ValidationException::withMessages(['manufacturing_order' => (string) __('print.error.order_already_specialized')]);
        }

        $order = PrintOrder::query()->create([
            'organization_id' => $manufacturingOrder->organization_id,
            'manufacturing_order_id' => $manufacturingOrder->id,
            'status' => PrintOrderStatus::DataCheck,
            'output_kind' => PrintOutputKind::from((string) ($attributes['output_kind'] ?? PrintOutputKind::Pickup->value)),
            'files_retain_until' => $attributes['files_retain_until'] ?? null,
            'created_by' => $actor->id,
        ]);
        $order->audit('print.order_opened', ['manufacturing_order_id' => $manufacturingOrder->id]);

        return $order;
    }

    /**
     * Produktionsdatei binden: SHA-256 sichern; eine geänderte Datei setzt
     * Preflight UND Freigabe zurück (Auftrag wird wieder prüfpflichtig).
     */
    public function bindFile(PrintOrder $order, Document $document, DocumentVersion $version, User $actor): PrintOrder {
        if ($order->status->isFinal()) {
            throw ValidationException::withMessages(['status' => (string) __('print.error.order_closed')]);
        }
        if ($version->document_id !== $document->id || $document->organization_id !== $order->organization_id) {
            throw ValidationException::withMessages(['document' => (string) __('print.error.document_mismatch')]);
        }

        $hash = $this->hashVersion($version);
        $changed = $order->file_hash !== null && ! hash_equals($order->file_hash, $hash);

        return DB::transaction(function () use ($order, $document, $version, $actor, $hash, $changed): PrintOrder {
            $order->forceFill([
                'document_id' => $document->id,
                'document_version_id' => $version->id,
                'file_hash' => $hash,
                'file_bound_at' => now(),
                'files_purged_at' => null,
                // Neue/geänderte Datei: Prüfung und Freigabe verfallen.
                'preflight_status' => PreflightStatus::Pending,
                'preflight_provider' => null,
                'preflight_findings' => null,
                'preflight_at' => null,
                'preflight_by' => null,
                'preflight_override_reason' => null,
                'preflight_overridden_by' => null,
                'preflight_overridden_at' => null,
            ]);

            // Andere Datei: Kundenfreigabe und deren Anforderung verfallen (MVP-1076).
            if ($changed) {
                $order->forceFill([
                    'customer_approval_requested_at' => null,
                    'customer_approval_request' => null,
                    'customer_approved_at' => null,
                    'customer_approval_user_id' => null,
                    'customer_approved_file_hash' => null,
                    'customer_declined_at' => null,
                    'customer_decline_reason' => null,
                ]);
            }
            if ($changed && in_array($order->status, [PrintOrderStatus::Approved, PrintOrderStatus::Rework], true)) {
                $order->forceFill([
                    'status' => PrintOrderStatus::DataCheck,
                    'approved_at' => null,
                    'approved_by' => null,
                    'approved_file_hash' => null,
                    'production_snapshot' => null,
                ]);
            }
            $order->save();

            $order->audit('print.file_bound', [
                'document_version_id' => $version->id,
                'file_hash' => $hash,
                'reset_approval' => $changed,
                'by' => $actor->id,
            ]);

            return $order;
        });
    }

    /**
     * Produktionsdatei aus einem Kundeneingang festlegen (MVP-1076): die
     * gewählte Datei wird als neue Dokumentversion des Auftrags kopiert und
     * mit Hash gebunden; der Kundennachweis am Eingang bleibt unverändert.
     */
    public function bindIntakeFile(PrintOrder $order, Attachment $attachment, User $actor): PrintOrder {
        if ($order->status->isFinal()) {
            throw ValidationException::withMessages(['status' => (string) __('print.error.order_closed')]);
        }

        $document = $order->document;
        if ($document === null) {
            $document = $this->documents->createFromStoredFile(null, $actor, [
                'title' => (string) __('print.document_title', ['number' => (string) $order->manufacturingOrder?->number]),
                'document_type' => 'other',
            ], $attachment->disk, $attachment->path, $attachment->original_name, $attachment->mime);
            $version = $document->versions()->orderByDesc('version_no')->firstOrFail();
        } else {
            $version = $this->documents->addVersionFromStoredFile($document, $actor, $attachment->disk, $attachment->path, $attachment->original_name, $attachment->mime, (string) __('print.intake.version_note'));
        }

        $order = $this->bindFile($order, $document, $version, $actor);
        $order->audit('print.intake_file_bound', ['attachment_id' => $attachment->id, 'original_name' => $attachment->original_name, 'by' => $actor->id]);

        return $order;
    }

    /**
     * Kundenfreigabe anfordern (MVP-1076): friert Dateiversion, Prüfsumme und
     * Parameter ein; eine frühere Entscheidung verfällt.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function requestCustomerApproval(PrintOrder $order, array $parameters, User $actor): PrintOrder {
        if ($order->status !== PrintOrderStatus::DataCheck) {
            throw ValidationException::withMessages(['status' => (string) __('print.error.customer_approval_status')]);
        }
        if (! $order->hasProductionFile() || $order->file_hash === null) {
            throw ValidationException::withMessages(['document' => (string) __('print.error.file_required')]);
        }
        if (! $order->preflight_status->allowsApproval()) {
            throw ValidationException::withMessages(['preflight' => (string) __('print.error.preflight_blocks_approval')]);
        }
        foreach (self::CUSTOMER_PARAMETERS as $required) {
            if (trim((string) ($parameters[$required] ?? '')) === '') {
                throw ValidationException::withMessages([$required => (string) __('print.error.parameter_required', ['parameter' => (string) __('print.snapshot.' . $required)])]);
            }
        }

        $version = $order->documentVersion;
        $request = [
            'file' => [
                'document_version_id' => $order->document_version_id,
                'version_no' => $version?->version_no,
                'sha256' => $order->file_hash,
                'original_name' => $version?->original_name,
            ],
            'parameters' => [
                'final_format' => trim((string) $parameters['final_format']),
                'quantity' => trim((string) $parameters['quantity']),
                'color_mode' => trim((string) $parameters['color_mode']),
                'material' => trim((string) $parameters['material']),
                'pages' => isset($parameters['pages']) && $parameters['pages'] !== '' ? (int) $parameters['pages'] : null,
                'finishing' => array_values((array) ($parameters['finishing'] ?? [])),
            ],
        ];

        $order->forceFill([
            'customer_approval_requested_at' => now(),
            'customer_approval_request' => $request,
            'customer_approved_at' => null,
            'customer_approval_user_id' => null,
            'customer_approved_file_hash' => null,
            'customer_declined_at' => null,
            'customer_decline_reason' => null,
        ])->save();
        $order->audit('print.customer_approval_requested', ['file_hash' => $order->file_hash, 'by' => $actor->id]);

        return $order;
    }

    /**
     * Entscheidung des Kunden zur angeforderten Freigabe: gilt nur, solange
     * die gebundene Datei die angeforderte ist. Person, Zeitpunkt und Hash
     * werden festgehalten; die interne Produktionsfreigabe bleibt getrennt.
     */
    public function recordCustomerDecision(PrintOrder $order, User $portalUser, bool $approved, ?string $reason = null): PrintOrder {
        if (! $order->customerApprovalPending()) {
            throw ValidationException::withMessages(['decision' => (string) __('print.error.customer_approval_not_pending')]);
        }
        $requested = (string) data_get($order->customer_approval_request, 'file.sha256', '');
        if ($order->file_hash === null || ! hash_equals($requested, $order->file_hash)) {
            throw ValidationException::withMessages(['decision' => (string) __('print.error.customer_approval_stale')]);
        }
        $reason = trim((string) $reason);
        if (! $approved && $reason === '') {
            throw ValidationException::withMessages(['reason' => (string) __('print.error.customer_decline_reason_required')]);
        }

        $order->forceFill($approved
            ? ['customer_approved_at' => now(), 'customer_approval_user_id' => $portalUser->id, 'customer_approved_file_hash' => $order->file_hash]
            : ['customer_declined_at' => now(), 'customer_approval_user_id' => $portalUser->id, 'customer_decline_reason' => $reason])->save();
        $order->audit($approved ? 'print.customer_approved' : 'print.customer_declined', [
            'file_hash' => $order->file_hash,
            'portal_user_id' => $portalUser->id,
            'reason' => $approved ? null : $reason,
        ]);

        return $order;
    }

    /** Preflight über den (austauschbaren) Provider ausführen. */
    public function runPreflight(PrintOrder $order, User $actor, ?PreflightProvider $provider = null): PrintOrder {
        $version = $order->documentVersion;
        if ($version === null || ! $order->hasProductionFile()) {
            throw ValidationException::withMessages(['document' => (string) __('print.error.file_required')]);
        }

        $provider ??= app(BasicPreflightProvider::class);
        if (! $provider->supports($version)) {
            throw ValidationException::withMessages(['document' => (string) __('print.error.provider_unsupported')]);
        }

        return $this->storePreflight($order, $provider->check($version), $actor);
    }

    /**
     * Manuell erhobenen Befund speichern (Sichtprüfung / externes Werkzeug
     * ohne Direktanbindung) — gleiche Semantik: Fehler blockieren.
     *
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function recordManualPreflight(PrintOrder $order, array $errors, array $warnings, User $actor): PrintOrder {
        if (! $order->hasProductionFile()) {
            throw ValidationException::withMessages(['document' => (string) __('print.error.file_required')]);
        }

        return $this->storePreflight($order, new PreflightReport('manual', $errors, $warnings), $actor);
    }

    /** Begründeter, auditierter Override blockierender Preflight-Fehler. */
    public function overridePreflight(PrintOrder $order, string $reason, User $actor): PrintOrder {
        if ($order->preflight_status !== PreflightStatus::Failed) {
            throw ValidationException::withMessages(['preflight' => (string) __('print.error.override_only_failed')]);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => (string) __('print.error.override_reason_required')]);
        }

        $order->forceFill([
            'preflight_status' => PreflightStatus::Overridden,
            'preflight_override_reason' => trim($reason),
            'preflight_overridden_by' => $actor->id,
            'preflight_overridden_at' => now(),
        ])->save();
        $order->audit('print.preflight_overridden', ['reason' => trim($reason), 'by' => $actor->id]);

        return $order;
    }

    /**
     * Druckfreigabe: Parameter müssen vollständig sein; Snapshot und
     * Datei-Hash werden unveränderlich an die Freigabe gebunden.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function approve(PrintOrder $order, array $parameters, User $actor): PrintOrder {
        $this->assertValidatedTransition($order->status, PrintOrderStatus::Approved, 'print.error.invalid_transition_detail');
        if (! $order->hasProductionFile() || $order->file_hash === null) {
            throw ValidationException::withMessages(['document' => (string) __('print.error.file_required')]);
        }
        if (! $order->preflight_status->allowsApproval()) {
            throw ValidationException::withMessages(['preflight' => (string) __('print.error.preflight_blocks_approval')]);
        }

        foreach (['final_format', 'material', 'quantity', 'color_mode', 'due_date'] as $required) {
            if (trim((string) ($parameters[$required] ?? '')) === '') {
                throw ValidationException::withMessages([$required => (string) __('print.error.parameter_required', ['parameter' => (string) __('print.snapshot.' . $required)])]);
            }
        }
        if ($order->is_customer_approval_required) {
            $this->assertCustomerApprovalCovers($order, $parameters);
        }

        $manufacturing = $order->manufacturingOrder;
        $snapshot = [
            'file' => [
                'document_id' => $order->document_id,
                'document_version_id' => $order->document_version_id,
                'sha256' => $order->file_hash,
                'original_name' => $order->documentVersion?->original_name,
            ],
            'final_format' => (string) $parameters['final_format'],
            'pages' => isset($parameters['pages']) ? (int) $parameters['pages'] : null,
            'orientation' => $parameters['orientation'] ?? null,
            'bleed_mm' => $parameters['bleed_mm'] ?? null,
            'safety_mm' => $parameters['safety_mm'] ?? null,
            'color_mode' => (string) $parameters['color_mode'],
            'color_profile' => $parameters['color_profile'] ?? null,
            'spot_colors' => $parameters['spot_colors'] ?? null,
            'material' => (string) $parameters['material'],
            'grammage' => $parameters['grammage'] ?? null,
            'quantity' => (string) $parameters['quantity'],
            'due_date' => (string) $parameters['due_date'],
            'finishing' => array_values((array) ($parameters['finishing'] ?? [])),
            'output_kind' => $order->output_kind->value,
            'manufacturing_order_number' => $manufacturing?->number,
            'approved_by' => $actor->id,
            'approved_at' => now()->toIso8601String(),
        ];

        $order->forceFill([
            'status' => PrintOrderStatus::Approved,
            'production_snapshot' => $snapshot,
            'approved_at' => now(),
            'approved_by' => $actor->id,
            'approved_file_hash' => $order->file_hash,
        ])->save();
        $order->audit('print.order_approved', ['file_hash' => $order->file_hash, 'by' => $actor->id]);

        return $order;
    }

    /**
     * Produktionsstart: Freigabe muss zur gebundenen Datei passen, die
     * Maschine darf weder gesperrt noch prüf-/kalibrierüberfällig sein.
     */
    public function startProduction(PrintOrder $order, ?Asset $machine, User $actor): PrintOrder {
        $this->assertValidatedTransition($order->status, PrintOrderStatus::InProduction, 'print.error.invalid_transition_detail');
        if (! $order->approvalMatchesFile()) {
            throw ValidationException::withMessages(['approval' => (string) __('print.error.approval_stale')]);
        }

        if ($machine !== null) {
            if ($machine->organization_id !== $order->organization_id) {
                throw ValidationException::withMessages(['asset' => (string) __('print.error.machine_foreign')]);
            }
            $this->assetGuard->ensureUsable($machine, self::ASSET_CONTEXT);
            $complianceStatus = $this->compliance->statusFor($machine);
            if (in_array($complianceStatus, [AssetComplianceStatus::Blocked, AssetComplianceStatus::Overdue], true)) {
                throw ValidationException::withMessages(['asset' => (string) __('print.error.machine_inspection_overdue')]);
            }
        }

        $order->forceFill([
            'status' => PrintOrderStatus::InProduction,
            'asset_id' => $machine?->id,
            'production_started_at' => now(),
            'production_started_by' => $actor->id,
        ])->save();
        $order->audit('print.production_started', ['asset_id' => $machine?->id, 'by' => $actor->id]);

        return $order;
    }

    /**
     * Qualitätskontrolle gegen Freigabestand: Freigabe, Sperre oder
     * Nacharbeit — immer dokumentiert.
     */
    public function qualityCheck(PrintOrder $order, PrintQcStatus $result, ?string $note, User $actor): PrintOrder {
        if ($order->status === PrintOrderStatus::InProduction) {
            $this->assertValidatedTransition($order->status, PrintOrderStatus::QualityCheck, 'print.error.invalid_transition_detail');
            $order->forceFill(['status' => PrintOrderStatus::QualityCheck])->save();
        }
        if ($order->status !== PrintOrderStatus::QualityCheck) {
            throw ValidationException::withMessages(['status' => (string) __('print.error.invalid_transition')]);
        }

        $target = match ($result) {
            PrintQcStatus::Passed => PrintOrderStatus::Ready,
            PrintQcStatus::Rework => PrintOrderStatus::Rework,
            PrintQcStatus::Blocked => PrintOrderStatus::QualityCheck, // Sperre: bleibt in QK
        };
        if ($target !== PrintOrderStatus::QualityCheck) {
            $this->assertValidatedTransition($order->status, $target, 'print.error.invalid_transition_detail');
        }

        $order->forceFill([
            'status' => $target,
            'qc_status' => $result,
            'qc_at' => now(),
            'qc_by' => $actor->id,
            'qc_note' => trim((string) $note) ?: null,
        ])->save();
        $order->audit('print.quality_checked', ['result' => $result->value, 'by' => $actor->id]);

        return $order;
    }

    /** Nacharbeit zurück in die Produktion (gleicher Freigabestand). */
    public function resumeProduction(PrintOrder $order, User $actor): PrintOrder {
        if ($order->status !== PrintOrderStatus::Rework) {
            throw ValidationException::withMessages(['status' => (string) __('print.error.invalid_transition')]);
        }
        if (! $order->approvalMatchesFile()) {
            throw ValidationException::withMessages(['approval' => (string) __('print.error.approval_stale')]);
        }

        $order->forceFill(['status' => PrintOrderStatus::InProduction])->save();
        $order->audit('print.production_resumed', ['by' => $actor->id]);

        return $order;
    }

    /**
     * Ausgabe: Abholung (Übergabenachweis), Versand (vorhandene Sendung)
     * oder datensparsamer Tresenverkauf.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function issue(PrintOrder $order, array $attributes, User $actor): PrintOrder {
        $this->assertValidatedTransition($order->status, PrintOrderStatus::Issued, 'print.error.invalid_transition_detail');

        $shipment = null;
        if ($order->output_kind === PrintOutputKind::Shipping) {
            $shipment = $attributes['shipment'] ?? null;
            if (! $shipment instanceof Shipment || $shipment->organization_id !== $order->organization_id) {
                throw ValidationException::withMessages(['shipment' => (string) __('print.error.shipment_required')]);
            }
        }
        if ($order->output_kind === PrintOutputKind::Pickup && trim((string) ($attributes['handover_name'] ?? '')) === '') {
            throw ValidationException::withMessages(['handover_name' => (string) __('print.error.handover_required')]);
        }

        $order->forceFill([
            'status' => PrintOrderStatus::Issued,
            'issued_at' => now(),
            'issued_by' => $actor->id,
            // Datensparsam: Personenbezug nur bei Abholung, nie am Tresen.
            'handover_name' => trim((string) ($attributes['handover_name'] ?? '')) ?: null,
            'handover_note' => trim((string) ($attributes['handover_note'] ?? '')) ?: null,
            'shipment_id' => $shipment?->id,
        ])->save();
        $order->audit('print.order_issued', ['output_kind' => $order->output_kind->value, 'by' => $actor->id]);

        return $order;
    }

    /** Storno mit Begründung (kein stiller Abbruch). */
    public function cancel(PrintOrder $order, string $reason, User $actor): PrintOrder {
        $this->assertValidatedTransition($order->status, PrintOrderStatus::Cancelled, 'print.error.invalid_transition_detail');
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => (string) __('print.error.cancel_reason_required')]);
        }

        $order->forceFill([
            'status' => PrintOrderStatus::Cancelled,
            'cancel_reason' => trim($reason),
        ])->save();
        $order->audit('print.order_cancelled', ['reason' => trim($reason), 'by' => $actor->id]);

        return $order;
    }

    /**
     * Löschfrist durchsetzen: entfernt die gespeicherten Produktionsdateien
     * (alle Versionen) tenant-sicher aus dem Storage — Auftrag, Snapshot und
     * Hash bleiben als kaufmännischer Nachweis erhalten.
     *
     * @return int Anzahl bereinigter Druckaufträge
     */
    public function purgeExpiredFiles(?Organization $organization = null): int {
        $query = PrintOrder::query()
            ->withoutGlobalScopes()
            ->whereNotNull('files_retain_until')
            ->whereNull('files_purged_at')
            ->whereNotNull('document_id')
            ->whereDate('files_retain_until', '<', now()->toDateString());
        if ($organization !== null) {
            $query->where('organization_id', $organization->id);
        }

        $purged = 0;
        foreach ($query->with('document')->get() as $order) {
            /** @var PrintOrder $order */
            $document = $order->document()->withoutGlobalScopes()->first();
            if ($document !== null) {
                foreach ($document->versions()->get() as $version) {
                    /** @var DocumentVersion $version */
                    Storage::disk($version->disk)->delete($version->path);
                }
            }
            $order->forceFill(['files_purged_at' => now()])->save();
            $order->audit('print.files_purged', ['retained_until' => $order->files_retain_until?->toDateString()]);
            $purged++;
        }

        return $purged;
    }

    /**
     * Interne Freigabe eines Auftrags mit Kundenpflicht: gültige Kundenfreigabe
     * für die gebundene Datei und dieselben freigegebenen Parameter.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function assertCustomerApprovalCovers(PrintOrder $order, array $parameters): void {
        if (! $order->customerApprovalMatchesFile()) {
            throw ValidationException::withMessages(['approval' => (string) __('print.error.customer_approval_missing')]);
        }
        $approved = (array) data_get($order->customer_approval_request, 'parameters', []);
        foreach (self::CUSTOMER_PARAMETERS as $key) {
            $given = trim((string) ($parameters[$key] ?? ''));
            $expected = trim((string) ($approved[$key] ?? ''));
            $same = $key === 'quantity' && is_numeric($given) && is_numeric($expected)
                ? (float) $given === (float) $expected
                : mb_strtolower($given) === mb_strtolower($expected);
            if (! $same) {
                throw ValidationException::withMessages([$key => (string) __('print.error.customer_parameter_mismatch', ['parameter' => (string) __('print.snapshot.' . $key), 'approved' => $expected])]);
            }
        }
    }

    private function storePreflight(PrintOrder $order, PreflightReport $report, User $actor): PrintOrder {
        $order->forceFill([
            'preflight_status' => $report->status(),
            'preflight_provider' => $report->provider,
            'preflight_findings' => $report->findings(),
            'preflight_at' => now(),
            'preflight_by' => $actor->id,
            'preflight_override_reason' => null,
            'preflight_overridden_by' => null,
            'preflight_overridden_at' => null,
        ])->save();
        $order->audit('print.preflight_recorded', [
            'provider' => $report->provider,
            'status' => $report->status()->value,
            'errors' => count($report->errors),
            'warnings' => count($report->warnings),
            'by' => $actor->id,
        ]);

        return $order;
    }

    /** SHA-256 der gespeicherten Dateiversion (Stream, speicherschonend). */
    private function hashVersion(DocumentVersion $version): string {
        $disk = Storage::disk($version->disk);
        if (! $disk->exists($version->path)) {
            throw ValidationException::withMessages(['document' => (string) __('print.error.file_missing_storage')]);
        }

        $stream = $disk->readStream($version->path);
        if ($stream === null) {
            throw ValidationException::withMessages(['document' => (string) __('print.error.file_missing_storage')]);
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return hash_final($context);
    }

}
