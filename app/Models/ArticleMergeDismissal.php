<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleMergeDismissal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Ein vom Anwender als „kein Duplikat" markiertes Artikel-Paar (Audit
 * 2026-08, W2.9). Normalisiert (low_id < high_id).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $article_low_id
 * @property int $article_high_id
 * @property int|null $dismissed_by
 */
class ArticleMergeDismissal extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'article_low_id',
        'article_high_id',
        'dismissed_by',
    ];

    /**
     * Normalisierter Schlüssel für ein Artikel-Paar (kleinere ID zuerst).
     *
     * @return array{article_low_id: int, article_high_id: int}
     */
    public static function pairKey(int $a, int $b): array {
        return [
            'article_low_id' => min($a, $b),
            'article_high_id' => max($a, $b),
        ];
    }
}
