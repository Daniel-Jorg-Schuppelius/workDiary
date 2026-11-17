<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeSearchGap.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Suchlücke (Feature 191, MVP-1667): ein Suchbegriff ohne Treffer in den
 * Wissensseiten. Gezählt wird der Begriff, nicht wer ihn gesucht hat — die
 * Tabelle kennt keine Person.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $term
 * @property int $searches
 * @property Carbon $first_searched_at
 * @property Carbon $last_searched_at
 * @property Carbon|null $dismissed_at
 */
class KnowledgeSearchGap extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'term',
        'searches',
        'first_searched_at',
        'last_searched_at',
        'dismissed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'searches' => 'integer',
        'first_searched_at' => 'datetime',
        'last_searched_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];
}
