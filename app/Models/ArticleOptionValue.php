<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleOptionValue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zulässiger Optionswert (Feature 048, MVP-060), z. B. „Rot". Wird deaktiviert
 * statt gelöscht, sobald er nicht mehr zulässig ist.
 *
 * @property int $id
 * @property int $article_option_definition_id
 * @property string $code
 * @property string $label
 * @property bool $active
 */
class ArticleOptionValue extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'article_option_definition_id',
        'code',
        'label',
        'position',
        'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'active' => 'boolean',
        'position' => 'integer',
    ];

    /** @return BelongsTo<ArticleOptionDefinition, $this> */
    public function definition(): BelongsTo {
        return $this->belongsTo(ArticleOptionDefinition::class, 'article_option_definition_id');
    }
}
