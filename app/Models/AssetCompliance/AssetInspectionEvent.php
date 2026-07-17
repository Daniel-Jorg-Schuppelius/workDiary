<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetCompliance;

use App\Enums\AssetCompliance\AssetInspectionResult;
use App\Models\{Asset, User};
use App\Models\Concerns\{AppendOnly, Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/**
 * Durchgeführte Prüfung (MVP-286): unveränderbarer Nachweis mit Ergebnis,
 * Messwerten, Gültigkeit und Unterschrift. Korrekturen erfolgen versioniert
 * über supersedes_id — nie durch Änderung des Originals.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $asset_inspection_schedule_id
 * @property int|null $asset_compliance_assignment_id
 * @property int $asset_id
 * @property \Illuminate\Support\Carbon $performed_at
 * @property AssetInspectionResult $result
 * @property \Illuminate\Support\Carbon|null $valid_until
 */
class AssetInspectionEvent extends Model {
    // Unveränderbarer Nachweis: nur versionierte Korrekturen (neues Event mit supersedes_id).
    use AppendOnly;

    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'asset_inspection_schedule_id',
        'asset_compliance_assignment_id', 'asset_id', 'performed_at',
        'performed_by_user_id', 'external_inspector_name', 'result',
        'valid_until', 'checklist', 'signature_name', 'signed_at', 'note',
        'supersedes_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'result' => AssetInspectionResult::class,
        'performed_at' => 'datetime',
        'valid_until' => 'date',
        'checklist' => 'array',
        'signed_at' => 'datetime',
    ];

    /** @return BelongsTo<AssetInspectionSchedule, $this> */
    public function schedule(): BelongsTo {
        return $this->belongsTo(AssetInspectionSchedule::class, 'asset_inspection_schedule_id');
    }

    /** @return BelongsTo<AssetComplianceAssignment, $this> */
    public function assignment(): BelongsTo {
        return $this->belongsTo(AssetComplianceAssignment::class, 'asset_compliance_assignment_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function performer(): BelongsTo {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    /** @return HasMany<AssetInspectionResultLine, $this> */
    public function results(): HasMany {
        return $this->hasMany(AssetInspectionResultLine::class, 'asset_inspection_event_id');
    }

    /** @return HasMany<AssetMeasurementValue, $this> */
    public function measurements(): HasMany {
        return $this->hasMany(AssetMeasurementValue::class, 'asset_inspection_event_id');
    }

    /** @return HasOne<AssetCalibrationCertificate, $this> */
    public function certificate(): HasOne {
        return $this->hasOne(AssetCalibrationCertificate::class, 'asset_inspection_event_id');
    }

    /** @return BelongsTo<self, $this> */
    public function supersedes(): BelongsTo {
        return $this->belongsTo(self::class, 'supersedes_id');
    }
}
