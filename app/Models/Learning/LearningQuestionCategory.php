<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuestionCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fragenkategorie des Katalogs (Feature 149, MVP-782): ordnet Fragen und
 * ist die Bezugsgröße der Ziehregeln („5 aus Brandschutz").
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property int $position
 */
class LearningQuestionCategory extends Model {
    use Auditable;

    use BelongsToOrganization;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    /** @return HasMany<LearningQuestion, $this> */
    public function questions(): HasMany {
        return $this->hasMany(LearningQuestion::class, 'learning_question_category_id');
    }

    /** @return HasMany<LearningQuizDrawRule, $this> */
    public function drawRules(): HasMany {
        return $this->hasMany(LearningQuizDrawRule::class, 'learning_question_category_id');
    }
}
