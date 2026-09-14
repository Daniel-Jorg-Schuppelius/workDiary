<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein fremdes Tool, das WorkDiary als LTI-Plattform startet (Feature 149).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $client_id
 * @property string $deployment_id
 * @property string $login_url
 * @property string $launch_url
 * @property list<string> $redirect_uris
 * @property string|null $deep_linking_url
 * @property string|null $jwks_url
 * @property string|null $public_jwks
 * @property bool $share_name
 * @property bool $share_email
 * @property bool $is_active
 */
class LearningLtiTool extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'client_id',
        'deployment_id',
        'login_url',
        'launch_url',
        'redirect_uris',
        'deep_linking_url',
        'jwks_url',
        'public_jwks',
        'share_name',
        'share_email',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'redirect_uris' => 'array',
        'share_name' => 'boolean',
        'share_email' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<LearningLtiLink, $this> */
    public function links(): HasMany {
        return $this->hasMany(LearningLtiLink::class, 'learning_lti_tool_id');
    }
}
