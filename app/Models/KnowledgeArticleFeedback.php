<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeArticleFeedback.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feedback „Hat geholfen / Hat nicht geholfen" — genau eine Wertung pro
 * User und Artikel (Unique-Index knowledge_feedback_uq). Mandantengrenze
 * transitiv über den tenant-gebundenen Artikel (siehe Tenant-Audit).
 *
 * @property int $id
 * @property int $knowledge_article_id
 * @property int $user_id
 * @property bool $helpful
 */
class KnowledgeArticleFeedback extends Model {
    public const UPDATED_AT = null;

    protected $table = 'knowledge_article_feedback';

    protected $fillable = [
        'knowledge_article_id',
        'user_id',
        'helpful',
    ];

    protected $casts = [
        'helpful' => 'boolean',
    ];

    /** @return BelongsTo<KnowledgeArticle, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(KnowledgeArticle::class, 'knowledge_article_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
