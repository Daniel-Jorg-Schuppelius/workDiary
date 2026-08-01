<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Print;

use App\Enums\Print\{PreflightStatus, PrintOrderStatus, PrintOutputKind};
use App\Models\{Asset, Document, DocumentVersion, ManufacturingOrder, Shipment, User};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Druckauftrag (MVP-459): 1:1-Spezialisierung eines {@see ManufacturingOrder}
 * — Mengen/Material/Lager/Nachkalkulation bleiben in der Fertigung, hier
 * liegen die drucktypischen Nachweise (Datei-Hash, Preflight, Freigabe,
 * Maschinen-Gate, QK, Ausgabe, Löschfrist).
 *
 * Datenschutz: `handover_name`/`handover_note` sind encrypted at-rest und
 * bleiben leer, wo die Ausgabe ohne Personenbezug auskommt (Tresen).
 * Leere Strings NIE speichern — "" bricht decrypt (Projektregel).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $manufacturing_order_id
 * @property PrintOrderStatus $status
 * @property PrintOutputKind $output_kind
 * @property int|null $document_id
 * @property int|null $document_version_id
 * @property string|null $file_hash
 * @property Carbon|null $file_bound_at
 * @property PreflightStatus $preflight_status
 * @property string|null $preflight_provider
 * @property array{errors?: list<string>, warnings?: list<string>}|null $preflight_findings
 * @property Carbon|null $preflight_at
 * @property string|null $preflight_override_reason
 * @property array<string, mixed>|null $production_snapshot
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property string|null $approved_file_hash
 * @property int|null $asset_id
 * @property Carbon|null $production_started_at
 * @property string|null $qc_status
 * @property Carbon|null $qc_at
 * @property string|null $qc_note
 * @property Carbon|null $issued_at
 * @property string|null $handover_name
 * @property int|null $shipment_id
 * @property Carbon|null $files_retain_until
 * @property Carbon|null $files_purged_at
 */
class PrintOrder extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    public const QC_PASSED = 'passed';

    public const QC_REWORK = 'rework';

    public const QC_BLOCKED = 'blocked';

    protected $fillable = [
        'organization_id',
        'manufacturing_order_id',
        'status',
        'output_kind',
        'document_id',
        'document_version_id',
        'file_hash',
        'file_bound_at',
        'preflight_status',
        'preflight_provider',
        'preflight_findings',
        'preflight_at',
        'preflight_by',
        'preflight_override_reason',
        'preflight_overridden_by',
        'preflight_overridden_at',
        'production_snapshot',
        'approved_at',
        'approved_by',
        'approved_file_hash',
        'asset_id',
        'production_started_at',
        'production_started_by',
        'qc_status',
        'qc_at',
        'qc_by',
        'qc_note',
        'issued_at',
        'issued_by',
        'handover_name',
        'handover_note',
        'shipment_id',
        'files_retain_until',
        'files_purged_at',
        'cancel_reason',
        'created_by',
    ];

    protected $casts = [
        'status' => PrintOrderStatus::class,
        'output_kind' => PrintOutputKind::class,
        'preflight_status' => PreflightStatus::class,
        'preflight_findings' => 'array',
        'production_snapshot' => 'array',
        'handover_name' => 'encrypted',
        'handover_note' => 'encrypted',
        'file_bound_at' => 'datetime',
        'preflight_at' => 'datetime',
        'preflight_overridden_at' => 'datetime',
        'approved_at' => 'datetime',
        'production_started_at' => 'datetime',
        'qc_at' => 'datetime',
        'issued_at' => 'datetime',
        'files_retain_until' => 'date',
        'files_purged_at' => 'datetime',
    ];

    /** @return BelongsTo<ManufacturingOrder, $this> */
    public function manufacturingOrder(): BelongsTo {
        return $this->belongsTo(ManufacturingOrder::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function documentVersion(): BelongsTo {
        return $this->belongsTo(DocumentVersion::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Shipment, $this> */
    public function shipment(): BelongsTo {
        return $this->belongsTo(Shipment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function qcChecker(): BelongsTo {
        return $this->belongsTo(User::class, 'qc_by');
    }

    /** @param Builder<PrintOrder> $query */
    public function scopeOpen(Builder $query): void {
        $query->whereNotIn('status', [
            PrintOrderStatus::Issued->value,
            PrintOrderStatus::Cancelled->value,
        ]);
    }

    /** Freigabe gültig: Hash der gebundenen Datei == freigegebener Hash. */
    public function approvalMatchesFile(): bool {
        return $this->approved_file_hash !== null
            && $this->file_hash !== null
            && hash_equals($this->approved_file_hash, $this->file_hash);
    }

    /** Produktionsdatei noch vorhanden (nicht durch Löschfrist entfernt)? */
    public function hasProductionFile(): bool {
        return $this->document_version_id !== null && $this->files_purged_at === null;
    }
}
