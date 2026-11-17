<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageRead.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lesebestätigung einer Fassung (Feature 191, MVP-1666) — Muster der
 * Kenntnisnahme in der Personalakte ({@see \App\Models\Hr\PersonnelFileAcknowledgement}):
 * sie gilt für den Text, den jemand gelesen hat; eine neue freigegebene
 * Fassung verlangt eine neue Bestätigung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $knowledge_page_id
 * @property int $knowledge_page_version_id
 * @property int $version
 * @property int $user_id
 * @property Carbon $confirmed_at
 */
class KnowledgePageRead extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'knowledge_page_id',
        'knowledge_page_version_id',
        'version',
        'user_id',
        'confirmed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'version' => 'integer',
        'confirmed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
