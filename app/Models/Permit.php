<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Permit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Permit\PermitStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use Database\Factories\PermitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Behördliche Genehmigung für eine Veranstaltung (Genehmigungs-Register).
 * Nachweis-Dokumente hängen polymorph über {@see HasAttachments}
 * (meta_type='evidence').
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $event_id
 * @property string $title
 * @property string|null $permit_type
 * @property string|null $authority
 * @property string|null $reference_no
 * @property PermitStatus $status
 * @property Carbon|null $applied_at
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Permit extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    /** @use HasFactory<PermitFactory> */
    use HasFactory;
    use HasSqid;

    /** meta_type für das Nachweis-Dokument am Anhang. */
    public const EVIDENCE_META = 'evidence';

    protected $fillable = [
        'organization_id',
        'event_id',
        'title',
        'permit_type',
        'authority',
        'reference_no',
        'status',
        'applied_at',
        'valid_from',
        'valid_until',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => PermitStatus::class,
        'applied_at' => 'date',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** Liegt das Ablaufdatum in der Vergangenheit? */
    public function isOverdue(): bool {
        return $this->valid_until !== null
            && $this->status !== PermitStatus::Granted
            && $this->valid_until->isPast();
    }

    public function evidence(): ?Attachment {
        return $this->attachmentByMeta(self::EVIDENCE_META);
    }
}
