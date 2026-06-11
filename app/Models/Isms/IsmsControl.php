<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsControl.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\{ControlImplementationStatus, ControlSource};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Database\Factories\Isms\IsmsControlFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};

/**
 * ISMS-Maßnahme/Control (Feature 044, MVP 1): Eintrag im Maßnahmenkatalog
 * — Annex-A-Referenz (nur Code + Kurztitel, KEINE Normtexte) oder eigene
 * Maßnahme. Trägt die SoA-Aussage: anwendbar ja/nein, Begründung,
 * Umsetzungsstatus, Evidenz-Notiz (Regeln im ControlService).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $title
 * @property string|null $description
 * @property ControlSource $source
 * @property bool $applicable
 * @property string|null $justification
 * @property ControlImplementationStatus $implementation_status
 * @property string|null $evidence_note
 * @property int|null $owner_user_id
 */
class IsmsControl extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsControlFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'code',
        'title',
        'description',
        'source',
        'applicable',
        'justification',
        'implementation_status',
        'evidence_note',
        'owner_user_id',
    ];

    protected $casts = [
        'source' => ControlSource::class,
        'applicable' => 'boolean',
        'implementation_status' => ControlImplementationStatus::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsToMany<IsmsRisk, $this> */
    public function risks(): BelongsToMany {
        return $this->belongsToMany(IsmsRisk::class, 'isms_control_risk', 'control_id', 'risk_id');
    }

    /**
     * Anwendbare Controls (SoA: applicable = true).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApplicable(Builder $query): Builder {
        return $query->where('applicable', true);
    }
}
