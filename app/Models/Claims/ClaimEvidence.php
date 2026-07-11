<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimEvidence.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Claims;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Nachweiseintrag (MVP-249): Fotos, Protokolle, Seriennummern, Messwerte,
 * Nachrichten — Dateien hängen als Attachments an der Akte, hier steht
 * der fachliche Kontext (was belegt was).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $claim_case_id
 * @property string $kind
 * @property string $title
 * @property string|null $note
 * @property \Illuminate\Support\Carbon $recorded_at
 */
class ClaimEvidence extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'claim_evidence';

    public const KINDS = ['photo', 'protocol', 'document', 'serial', 'measurement', 'message', 'other'];

    protected $fillable = [
        'organization_id', 'claim_case_id', 'kind', 'title', 'note',
        'evidencable_type', 'evidencable_id', 'recorded_by', 'recorded_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    /** @return BelongsTo<ClaimCase, $this> */
    public function claimCase(): BelongsTo {
        return $this->belongsTo(ClaimCase::class);
    }

    /** @return MorphTo<\Illuminate\Database\Eloquent\Model, $this> */
    public function evidencable(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
