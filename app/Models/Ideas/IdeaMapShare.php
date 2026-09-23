<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapShare.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Ideas\IdeaShareRole;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Freigabe einer Ideenlandkarte an genau EINE Person ODER EIN Team
 * (Feature 054, MVP-107) mit Rolle viewer/editor. Der XOR-Invariant
 * (user_id ⊕ team_id) wird im {@see \App\Services\Ideas\IdeaMapService}
 * erzwungen; Teamfreigaben werden beim Zugriff gegen `team_user` aufgelöst.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $idea_map_id
 * @property int|null $user_id
 * @property int|null $team_id
 * @property IdeaShareRole $role
 * @property int|null $created_by
 */
class IdeaMapShare extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'idea_map_id',
        'user_id',
        'team_id',
        'role',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'role' => IdeaShareRole::class,
    ];

    /** @return BelongsTo<IdeaMap, $this> */
    public function map(): BelongsTo {
        return $this->belongsTo(IdeaMap::class, 'idea_map_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo {
        return $this->belongsTo(Team::class);
    }
}
