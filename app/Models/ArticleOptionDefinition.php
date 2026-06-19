<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleOptionDefinition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Optionsdefinition eines Artikels (Feature 048, MVP-060), z. B. „Farbe".
 * Mandantengrenze transitiv über den Artikel.
 *
 * @property int $id
 * @property int $article_id
 * @property string $code
 * @property string $name
 * @property bool $active
 */
class ArticleOptionDefinition extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'article_id',
        'code',
        'name',
        'position',
        'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'active' => 'boolean',
        'position' => 'integer',
    ];

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return HasMany<ArticleOptionValue, $this> */
    public function values(): HasMany {
        return $this->hasMany(ArticleOptionValue::class)->orderBy('position');
    }
}
