<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubResourceClosure.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sperrzeit einer Ressource (MVP-853): Witterung, Wartung, Fremdbelegung —
 * bestehende Belegungen werden markiert, nicht gelöscht.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_resource_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $reason
 * @property int|null $created_by_user_id
 */
class ClubResourceClosure extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'club_resource_id', 'starts_at', 'ends_at', 'reason', 'created_by_user_id'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    /** @return BelongsTo<ClubResource, $this> */
    public function resource(): BelongsTo {
        return $this->belongsTo(ClubResource::class, 'club_resource_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
