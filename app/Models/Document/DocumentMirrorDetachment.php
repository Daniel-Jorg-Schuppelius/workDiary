<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentMirrorDetachment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Document;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * „Spiegelung getrennt" eines Dokuments für ein Ablage-Ziel (MVP-1029):
 * die Konfliktauflösung trennt die Spiegelung je Ziel (WebDAV, SharePoint, …);
 * Observer und Spiegel-Dienst reihen für dieses Ziel nichts mehr ein.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $document_id
 * @property string $target Plugin-ID des Ablage-Ziels
 * @property Carbon $detached_at
 * @property int|null $detached_by_user_id
 */
class DocumentMirrorDetachment extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'document_id',
        'target',
        'detached_at',
        'detached_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'detached_at' => 'datetime',
    ];

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function detachedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'detached_by_user_id');
    }
}
