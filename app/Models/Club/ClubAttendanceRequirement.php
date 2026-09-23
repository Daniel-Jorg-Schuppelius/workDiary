<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAttendanceRequirement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubEventKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anwesenheitsanforderung (MVP-855): vom Verein konfigurierte Anzahl bestätigter
 * Anwesenheiten je Zeitraum (z. B. Schießnachweis) — keine gesetzlichen Schwellen im Code.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int|null $club_group_id
 * @property int|null $club_department_id
 * @property int $required_count
 * @property int $period_months
 * @property ClubEventKind|null $event_kind
 * @property bool $is_active
 * @property string|null $notes
 */
class ClubAttendanceRequirement extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'name', 'club_group_id', 'club_department_id', 'required_count', 'period_months', 'event_kind', 'is_active', 'notes'];

    protected $casts = ['required_count' => 'integer', 'period_months' => 'integer', 'event_kind' => ClubEventKind::class, 'is_active' => 'boolean'];

    /** @return BelongsTo<ClubGroup, $this> */
    public function group(): BelongsTo {
        return $this->belongsTo(ClubGroup::class, 'club_group_id');
    }

    /** @return BelongsTo<ClubDepartment, $this> */
    public function department(): BelongsTo {
        return $this->belongsTo(ClubDepartment::class, 'club_department_id');
    }
}
