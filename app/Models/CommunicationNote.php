<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNote.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType, CommunicationVisibility};
use App\Enums\User\Permission;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use Database\Factories\CommunicationNoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

/**
 * Kommunikationsnotiz (MVP-012): dokumentiertes Kommunikationsereignis
 * (Telefonat, E-Mail, Vor-Ort-Gespräch …) mit polymorphem Bezug auf
 * Auftrag, Kunde oder Projekt.
 *
 * @property int $id
 * @property int $organization_id
 * @property class-string $notable_type
 * @property int $notable_id
 * @property CommunicationNoteType $type
 * @property CommunicationDirection $direction
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property string $subject
 * @property string $body
 * @property string|null $result
 * @property string|null $next_action
 * @property \Illuminate\Support\Carbon|null $next_action_due_at
 * @property int|null $next_action_user_id
 * @property \Illuminate\Support\Carbon|null $next_action_completed_at
 * @property int|null $next_action_completed_by_user_id
 * @property CommunicationVisibility $visibility
 * @property bool $confidential
 * @property int $created_by_user_id
 */
class CommunicationNote extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasAttachments;
    /** @use HasFactory<CommunicationNoteFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'notable_type',
        'notable_id',
        'type',
        'direction',
        'occurred_at',
        'subject',
        'body',
        'result',
        'next_action',
        'next_action_due_at',
        'next_action_user_id',
        'next_action_completed_at',
        'next_action_completed_by_user_id',
        'visibility',
        'confidential',
        'created_by_user_id',
    ];

    protected $casts = [
        'type' => CommunicationNoteType::class,
        'direction' => CommunicationDirection::class,
        'visibility' => CommunicationVisibility::class,
        'confidential' => 'boolean',
        'occurred_at' => 'datetime',
        'next_action_due_at' => 'datetime',
        'next_action_completed_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function notable(): MorphTo {
        return $this->morphTo();
    }

    /** @return HasMany<CommunicationNoteParticipant, $this> */
    public function participants(): HasMany {
        return $this->hasMany(CommunicationNoteParticipant::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function nextActionUser(): BelongsTo {
        return $this->belongsTo(User::class, 'next_action_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function nextActionCompletedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'next_action_completed_by_user_id');
    }

    /**
     * Blendet vertrauliche Notizen anderer Erfasser aus, sofern der Benutzer
     * keine `communication.confidential.manage`-Permission besitzt.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder {
        if ($user->isAdmin() || $user->can(Permission::CommunicationConfidentialManage->value)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->where('confidential', false)
                ->orWhere('created_by_user_id', $user->id);
        });
    }

    /**
     * Offene Folgeaktionen (Folgeaktion erfasst, noch nicht erledigt).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpenFollowUps(Builder $query): Builder {
        return $query->whereNotNull('next_action')
            ->whereNull('next_action_completed_at');
    }

    public function hasOpenFollowUp(): bool {
        return $this->next_action !== null && $this->next_action_completed_at === null;
    }
}
