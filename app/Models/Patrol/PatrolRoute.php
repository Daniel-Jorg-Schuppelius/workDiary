<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolRoute.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Patrol;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Rundgangs-Route (Feature 089): geordnete Kontrollpunkte mit Soll-Fenstern
 * je Objekt.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property int|null $site_id
 * @property bool $active
 * @property int|null $created_by
 */
class PatrolRoute extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'name', 'site_id', 'active', 'created_by'];

    /** @var array<string, string> */
    protected $casts = ['active' => 'boolean'];

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo {
        return $this->belongsTo(Site::class);
    }

    /** @return HasMany<PatrolCheckpoint, $this> */
    public function checkpoints(): HasMany {
        return $this->hasMany(PatrolCheckpoint::class)->orderBy('position');
    }

    /** @return HasMany<PatrolRun, $this> */
    public function runs(): HasMany {
        return $this->hasMany(PatrolRun::class);
    }
}
