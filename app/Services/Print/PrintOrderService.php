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
use App\Models\{Asset, Document, DocumentVersion, ManufacturingOrder, Organization, Shipment, User};
use App\Models\Print\PrintOrder;
use App\Services\Asset\AssetUsageGuard;
use App\Services\AssetCompliance\AssetComplianceService;
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
    /** Branchenprofil-Code (Seed unter database/data/branchprofiles). */
    public const PROFILE_CODE = 'druck-kopiershop';

    /** Guard-Kontext für die Maschinen-Einsatzprüfung. */
    public const ASSET_CONTEXT = 'print_production';

    public function __construct(
        private readonly AssetUsageGuard $assetGuard,
        private readonly AssetComplianceService $compliance,
    ) {}

    /** Profil installiert? (Muster RecipeService — Kontext-Gate der UI.) */
    public function isPrintProfileActive(Organization $organization): bool {
        $settings = is_array($organization->settings) ? $organization->settings : [];
        if (($settings['branch_profile_code'] ?? null) === self::PROFILE_CODE) {
            return true;
        }

        return data_get($settings, 'branch_profile_versions.' . self::PROFILE_CODE) !== null;
    }

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
        $this->assertTransition($order, PrintOrderStatus::Approved);
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
        $this->assertTransition($order, PrintOrderStatus::InProduction);
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
    public function qualityCheck(PrintOrder $order, string $result, ?string $note, User $actor): PrintOrder {
        if (! in_array($result, [PrintOrder::QC_PASSED, PrintOrder::QC_REWORK, PrintOrder::QC_BLOCKED], true)) {
            throw ValidationException::withMessages(['result' => (string) __('print.error.qc_result_invalid')]);
        }
        if ($order->status === PrintOrderStatus::InProduction) {
            $this->assertTransition($order, PrintOrderStatus::QualityCheck);
            $order->forceFill(['status' => PrintOrderStatus::QualityCheck])->save();
        }
        if ($order->status !== PrintOrderStatus::QualityCheck) {
            throw ValidationException::withMessages(['status' => (string) __('print.error.invalid_transition')]);
        }

        $target = match ($result) {
            PrintOrder::QC_PASSED => PrintOrderStatus::Ready,
            PrintOrder::QC_REWORK => PrintOrderStatus::Rework,
            default => PrintOrderStatus::QualityCheck, // Sperre: bleibt in QK
        };
        if ($target !== PrintOrderStatus::QualityCheck) {
            $this->assertTransition($order, $target);
        }

        $order->forceFill([
            'status' => $target,
            'qc_status' => $result,
            'qc_at' => now(),
            'qc_by' => $actor->id,
            'qc_note' => trim((string) $note) ?: null,
        ])->save();
        $order->audit('print.quality_checked', ['result' => $result, 'by' => $actor->id]);

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
        $this->assertTransition($order, PrintOrderStatus::Issued);

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
        $this->assertTransition($order, PrintOrderStatus::Cancelled);
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

    private function assertTransition(PrintOrder $order, PrintOrderStatus $target): void {
        if (! $order->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => (string) __('print.error.invalid_transition_detail', [
                    'from' => $order->status->value,
                    'to' => $target->value,
                ]),
            ]);
        }
    }
}
