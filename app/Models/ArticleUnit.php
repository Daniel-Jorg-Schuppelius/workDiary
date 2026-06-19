<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleUnit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Article\ArticleUnitKind;
use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Artikelbezogene Einheit mit exaktem Faktor zur Basiseinheit (Feature 048,
 * MVP-060). Mandantengrenze transitiv über den Artikel.
 *
 * @property int $id
 * @property int $article_id
 * @property string $code
 * @property ArticleUnitKind $kind
 * @property string $factor_to_base
 * @property bool $active
 */
class ArticleUnit extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'article_id',
        'code',
        'label',
        'kind',
        'factor_to_base',
        'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => ArticleUnitKind::class,
        'factor_to_base' => 'decimal:8',
        'active' => 'boolean',
    ];

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }
}
