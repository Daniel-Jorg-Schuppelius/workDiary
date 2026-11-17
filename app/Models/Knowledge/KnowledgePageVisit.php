<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageVisit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Letzter Besuch einer Person auf einer Wissensseite (Feature 191,
 * MVP-1667): welche Fassung sie zuletzt gesehen hat — Grundlage für
 * „geändert seit Ihrem letzten Besuch“.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $knowledge_page_id
 * @property int $user_id
 * @property int $seen_version
 * @property Carbon $visited_at
 */
class KnowledgePageVisit extends Model {
    use BelongsToOrganization;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'knowledge_page_id',
        'user_id',
        'seen_version',
        'visited_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'seen_version' => 'integer',
        'visited_at' => 'datetime',
    ];
}
