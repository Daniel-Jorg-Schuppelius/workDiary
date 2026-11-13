<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageAssignment.php
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
 * Pflichtlektüre (Feature 191, MVP-1666): die Seite ist für alle internen
 * Mitglieder, eine Rolle, ein Team oder eine Person zu lesen. `is_onboarding`
 * nimmt sie in die Einarbeitungsliste auf.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $knowledge_page_id
 * @property KnowledgeAudienceKind $kind
 * @property string|null $role_name
 * @property int|null $team_id
 * @property int|null $user_id
 * @property bool $is_onboarding
 * @property int|null $created_by
 */
class KnowledgePageAssignment extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'knowledge_page_id',
        'kind',
        'role_name',
        'team_id',
        'user_id',
        'is_onboarding',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => KnowledgeAudienceKind::class,
        'is_onboarding' => 'boolean',
    ];

    /** @return BelongsTo<KnowledgePage, $this> */
    public function page(): BelongsTo {
        return $this->belongsTo(KnowledgePage::class, 'knowledge_page_id');
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
