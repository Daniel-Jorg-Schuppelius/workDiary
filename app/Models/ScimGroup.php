<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScimGroup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eine über SCIM 2.0 provisionierte Gruppe (Feature 057, MVP-121 → Rang 16).
 *
 * Die Gruppe ist die IdP-seitige Sammlung; ihre Mitglieder werden **nur dann**
 * in `team_user` gespiegelt, wenn die Gruppe explizit einem {@see Team}
 * zugeordnet ist ({@see $team_id}) — die Zuordnung ist ein bewusster
 * Admin-Schritt. SCIM vergibt weiterhin NIE Rollen. Die SCIM-`id` ist die Sqid.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $display_name
 * @property string|null $external_id
 * @property int|null $team_id
 * @property list<array{value: string, user_id: int|null}>|null $members
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ScimGroup extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'display_name',
        'external_id',
        'team_id',
        'members',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'members' => 'array',
    ];

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo {
        return $this->belongsTo(Team::class);
    }
}
