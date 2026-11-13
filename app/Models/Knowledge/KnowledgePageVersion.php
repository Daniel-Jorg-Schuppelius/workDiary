<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageVersion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Fassung einer Wissensseite (Feature 191, MVP-1664): Titel und Text so, wie
 * sie gespeichert wurden, bei Anleitungen zusätzlich die Schritte
 * (`steps`: Text, Bild-Anhang, Markierungen). Fassungen werden nie geändert —
 * nur ihre Freigabe wird vermerkt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $knowledge_page_id
 * @property int $version
 * @property string $title
 * @property string|null $body
 * @property list<array{body: string|null, attachment_id: int|null, markers: list<array<string, mixed>>}>|null $steps
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $created_at
 */
class KnowledgePageVersion extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'knowledge_page_id',
        'version',
        'title',
        'body',
        'steps',
        'note',
        'created_by',
        'approved_at',
        'approved_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'version' => 'integer',
        'steps' => 'array',
        'approved_at' => 'datetime',
    ];

    /** @return BelongsTo<KnowledgePage, $this> */
    public function page(): BelongsTo {
        return $this->belongsTo(KnowledgePage::class, 'knowledge_page_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
