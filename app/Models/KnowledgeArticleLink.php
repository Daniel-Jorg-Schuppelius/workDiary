<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeArticleLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\KnowledgeArticleLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Verknüpfung „Artikel hat bei diesem Auftrag/Asset/Kunden/Protokoll
 * geholfen" (Feature 011, Problemhistorie). Mandantengrenze transitiv
 * über den tenant-gebundenen Artikel (knowledge_articles.organization_id)
 * — analog CommunicationNoteParticipant, siehe Allow-List im Tenant-Audit.
 *
 * @property int $id
 * @property int $knowledge_article_id
 * @property string $linkable_type
 * @property int $linkable_id
 * @property int $created_by_user_id
 */
class KnowledgeArticleLink extends Model {
    /** @use HasFactory<KnowledgeArticleLinkFactory> */
    use HasFactory;

    use HasSqid;

    public const UPDATED_AT = null;

    protected $table = 'knowledge_article_links';

    protected $fillable = [
        'knowledge_article_id',
        'linkable_type',
        'linkable_id',
        'created_by_user_id',
    ];

    /** @return BelongsTo<KnowledgeArticle, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(KnowledgeArticle::class, 'knowledge_article_id');
    }

    /** @return MorphTo<Model, $this> */
    public function linkable(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
