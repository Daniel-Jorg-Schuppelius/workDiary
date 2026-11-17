<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionSchedule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetCompliance;

use App\Enums\AssetCompliance\AssetInspectionScheduleStatus;
use App\Models\Asset\Asset;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\Contacts\ExternalContact;
use App\Models\Contracts\PeriodicOccurrence;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Support\Period\PeriodKey;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prüftermin (MVP-285): geplante Durchführung mit internem Prüfer oder
 * externer Prüfstelle; externe Prüfer liefern Nachweise über einen
 * begrenzten Zugang (Feature 033). Attachments = angeforderte Unterlagen.
 *
 * Vorkommen der Merker für die nächste Periode (Feature 201, MVP-1646): Reihe
 * ist die Prüfpflicht ({@see AssetComplianceAssignment::carryForwardSeries()}),
 * Periode die Fälligkeit in der Art des Prüfintervalls. Ein Prüftermin ist ein
 * Einzeltermin (nach der Prüfung erledigt); die Pflicht verbindet die Termine.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_compliance_assignment_id
 * @property int $asset_id
 * @property int|null $diary_entry_id
 * @property \Illuminate\Support\Carbon $due_on
 * @property \Illuminate\Support\Carbon|null $planned_on
 * @property AssetInspectionScheduleStatus $status
 * @property string|null $cancel_reason
 */
class AssetInspectionSchedule extends Model implements PeriodicOccurrence {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'asset_compliance_assignment_id', 'asset_id', 'diary_entry_id',
        'due_on', 'planned_on', 'inspector_user_id', 'external_contact_id',
        'status', 'note', 'cancel_reason',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => AssetInspectionScheduleStatus::class,
        'due_on' => 'date',
        'planned_on' => 'date',
    ];

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void {
        $query->whereIn('status', [
            AssetInspectionScheduleStatus::Planned->value,
            AssetInspectionScheduleStatus::Announced->value,
            AssetInspectionScheduleStatus::InProgress->value,
        ]);
    }

    /** @return BelongsTo<AssetComplianceAssignment, $this> */
    public function assignment(): BelongsTo {
        return $this->belongsTo(AssetComplianceAssignment::class, 'asset_compliance_assignment_id');
    }

    public function carryForwardSeries(): ?string {
        return $this->assignment?->carryForwardSeries();
    }

    public function carryForwardPeriod(): ?PeriodKey {
        return $this->assignment?->carryForwardPeriodAt($this->due_on);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inspector(): BelongsTo {
        return $this->belongsTo(User::class, 'inspector_user_id');
    }

    /** @return BelongsTo<ExternalContact, $this> */
    public function externalContact(): BelongsTo {
        return $this->belongsTo(ExternalContact::class);
    }

    /**
     * Auftrag der Prüfertour (MVP-918).
     *
     * @return BelongsTo<DiaryEntry, $this>
     */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }
}
