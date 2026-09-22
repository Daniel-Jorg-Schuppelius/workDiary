<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubDepartment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\Club\ClubDepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Abteilung/Sparte eines Vereins (MVP-842): bündelt Gruppen; `discipline`
 * verknüpft später die Graduierungsordnung (MVP-846). Ein Mehrspartenverein
 * hat einen Mitgliederstamm — die Abteilung ordnet nur Gruppen.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $description
 * @property string|null $discipline
 * @property bool $is_active
 * @property int $sort_order
 */
class ClubDepartment extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<ClubDepartmentFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'discipline',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return HasMany<ClubGroup, $this> */
    public function groups(): HasMany {
        return $this->hasMany(ClubGroup::class)->orderBy('name');
    }
}
