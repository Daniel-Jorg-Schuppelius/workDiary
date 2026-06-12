<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsManagementReview.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\ReviewStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Database\Factories\Isms\IsmsManagementReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Managementbewertung (Feature 046, Inkrement C): Protokoll je
 * Geltungsbereich mit Eingaben, Entscheidungen und Folgemaßnahmen;
 * laufende Nummer je Organisation (review_no). Die Freigabe (draft →
 * approved) setzt approved_by_user_id + approved_at (046-Prinzip
 * „Freigabe mit Person/Zeitpunkt/Gegenstand"); danach ist die Bewertung
 * UNVERÄNDERLICH ({@see \App\Services\Isms\AuditService::updateReview()}).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $isms_scope_id
 * @property int $review_no
 * @property Carbon $held_on
 * @property string $participants
 * @property string $inputs
 * @property string $decisions
 * @property string|null $follow_ups
 * @property ReviewStatus $status
 * @property int|null $approved_by_user_id
 * @property Carbon|null $approved_at
 */
class IsmsManagementReview extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsManagementReviewFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'isms_scope_id',
        'review_no',
        'held_on',
        'participants',
        'inputs',
        'decisions',
        'follow_ups',
        'status',
        'approved_by_user_id',
        'approved_at',
    ];

    protected $casts = [
        'review_no' => 'integer',
        'held_on' => 'date',
        'status' => ReviewStatus::class,
        'approved_at' => 'datetime',
    ];

    /** Anzeige-Kennung, z. B. "MR-3" (analog IsmsRisk::displayNo()). */
    public function displayNo(): string {
        return 'MR-' . $this->review_no;
    }

    /** Freigegeben und damit unveränderlich? */
    public function isApproved(): bool {
        return $this->status === ReviewStatus::Approved;
    }

    /** @return BelongsTo<IsmsScope, $this> */
    public function scope(): BelongsTo {
        return $this->belongsTo(IsmsScope::class, 'isms_scope_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
