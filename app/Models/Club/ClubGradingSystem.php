<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGradingSystem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubGradingVersionStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Graduierungsordnung je Disziplin (MVP-846): benannte, geordnete Grade und
 * versionierte Voraussetzungen. Keine Verbandsvorgabe fest im Code.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $discipline
 * @property string|null $description
 * @property bool $is_active
 */
class ClubGradingSystem extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'name', 'discipline', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** @return HasMany<ClubGrade, $this> */
    public function grades(): HasMany {
        return $this->hasMany(ClubGrade::class)->orderBy('rank');
    }

    /** @return HasMany<ClubGradingVersion, $this> */
    public function versions(): HasMany {
        return $this->hasMany(ClubGradingVersion::class)->orderByDesc('version_no');
    }

    /** @return HasMany<ClubMemberGrade, $this> */
    public function memberGrades(): HasMany {
        return $this->hasMany(ClubMemberGrade::class);
    }

    public function activeVersion(): ?ClubGradingVersion {
        /** @var ClubGradingVersion|null $version */
        $version = $this->versions()->where('status', ClubGradingVersionStatus::Active->value)->first();

        return $version;
    }
}
