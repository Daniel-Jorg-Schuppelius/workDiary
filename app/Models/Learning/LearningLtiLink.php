<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verweis einer Lerneinheit auf einen Inhalt in einem LTI-Tool (Feature 149).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_unit_id
 * @property int $learning_lti_tool_id
 * @property string $resource_link_id
 * @property string|null $title
 * @property string|null $url
 * @property array<string, string>|null $custom
 * @property-read LearningLtiTool|null $tool
 */
class LearningLtiLink extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_unit_id',
        'learning_lti_tool_id',
        'resource_link_id',
        'title',
        'url',
        'custom',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'custom' => 'array',
    ];

    /** @return BelongsTo<LearningUnit, $this> */
    public function unit(): BelongsTo {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }

    /** @return BelongsTo<LearningLtiTool, $this> */
    public function tool(): BelongsTo {
        return $this->belongsTo(LearningLtiTool::class, 'learning_lti_tool_id');
    }
}
