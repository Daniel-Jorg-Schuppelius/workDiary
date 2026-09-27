<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisBusinessProcess.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Crisis;

use App\Enums\Crisis\CrisisProcessCriticality;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Geschäftsprozess im BIA-Register (MVP-943): Kritikalität, RTO/RPO/MTPD,
 * Abhängigkeiten und optional die Quelle (VVT, ISMS-Risiko, Prozedurvorlage).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $description
 * @property int|null $owner_user_id
 * @property CrisisProcessCriticality $criticality
 * @property int|null $rto_hours
 * @property int|null $rpo_hours
 * @property int|null $mtpd_hours
 * @property string|null $dependencies
 * @property string|null $source_type
 * @property int|null $source_id
 * @property Carbon|null $review_due_on
 * @property bool $is_active
 * @property int|null $created_by
 */
class CrisisBusinessProcess extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'name', 'description', 'owner_user_id', 'criticality', 'rto_hours', 'rpo_hours', 'mtpd_hours',
        'dependencies', 'source_type', 'source_id', 'review_due_on', 'is_active', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'criticality' => CrisisProcessCriticality::class,
        'rto_hours' => 'integer',
        'rpo_hours' => 'integer',
        'mtpd_hours' => 'integer',
        'review_due_on' => 'date',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo {
        return $this->morphTo();
    }
}
