<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeSpaceReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Enums\Knowledge\KnowledgeAudienceKind;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Platform\{Team, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Leser eines eingeschränkten Bereichs (Feature 191, MVP-1667): eine Rolle
 * (nach Namen, wie {@see \App\Services\Org\OrganizationRoles}), ein Team oder
 * eine Person.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $knowledge_space_id
 * @property KnowledgeAudienceKind $kind
 * @property string|null $role_name
 * @property int|null $team_id
 * @property int|null $user_id
 */
class KnowledgeSpaceReader extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'knowledge_space_id',
        'kind',
        'role_name',
        'team_id',
        'user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => KnowledgeAudienceKind::class,
    ];

    /** @return BelongsTo<KnowledgeSpace, $this> */
    public function space(): BelongsTo {
        return $this->belongsTo(KnowledgeSpace::class, 'knowledge_space_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
