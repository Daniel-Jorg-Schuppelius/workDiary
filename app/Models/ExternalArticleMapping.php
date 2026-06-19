<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalArticleMapping.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stabile Zuordnung eines internen Artikels/einer Variante zu einem externen
 * Provider-Artikel (Feature 048, MVP-060). JTL nutzt external_parent_id für den
 * Vaterartikel; Lexoffice bildet jede Variante als eigenständigen Artikel ab.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $plugin_id
 * @property string $external_id
 * @property string $sync_status
 */
class ExternalArticleMapping extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'plugin_id',
        'external_id',
        'article_id',
        'article_variant_id',
        'external_parent_id',
        'external_number',
        'unit',
        'sync_status',
        'last_synced_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }
}
