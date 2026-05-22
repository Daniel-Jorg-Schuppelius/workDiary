<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Event.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Event\EventStatus;
use App\Enums\Event\EventType;
use App\Enums\Event\EventVisibility;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAttachments;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property string|null $description
 * @property string|null $topic
 * @property EventType $event_type
 * @property int|null $category_id
 * @property Carbon $started_at
 * @property Carbon $ended_at
 * @property bool $is_all_day
 * @property string|null $timezone
 * @property EventStatus $status
 * @property EventVisibility $visibility
 * @property int|null $responsible_user_id
 * @property int|null $customer_id
 * @property string|null $external_contact_note
 * @property int|null $max_participants
 * @property bool $is_mandatory
 * @property int|null $certificate_valid_months
 * @property int|null $series_id
 * @property string|null $recurrence_rule
 * @property Carbon|null $series_until
 * @property array<int, int>|null $reminder_overrides
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Event extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;

    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'title',
        'description',
        'topic',
        'event_type',
        'category_id',
        'started_at',
        'ended_at',
        'is_all_day',
        'timezone',
        'status',
        'visibility',
        'responsible_user_id',
        'customer_id',
        'external_contact_note',
        'max_participants',
        'is_mandatory',
        'certificate_valid_months',
        'series_id',
        'recurrence_rule',
        'series_until',
        'reminder_overrides',
        'cancelled_at',
        'cancel_reason',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'event_type' => EventType::class,
        'status' => EventStatus::class,
        'visibility' => EventVisibility::class,
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_all_day' => 'boolean',
        'is_mandatory' => 'boolean',
        'series_until' => 'datetime',
        'reminder_overrides' => 'array',
        'cancelled_at' => 'datetime',
    ];

    /** @return BelongsTo<EventCategory, $this> */
    public function category(): BelongsTo {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsibleUser(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function series(): BelongsTo {
        return $this->belongsTo(self::class, 'series_id');
    }

    /** @return HasMany<Event, $this> */
    public function occurrences(): HasMany {
        return $this->hasMany(self::class, 'series_id');
    }

    /** @return BelongsToMany<Room, $this> */
    public function rooms(): BelongsToMany {
        return $this->belongsToMany(Room::class, 'event_room')
            ->withPivot(['started_at', 'ended_at', 'setup_minutes_before', 'teardown_minutes_after'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<User, $this, EventParticipant, 'pivot'> */
    public function participants(): BelongsToMany {
        return $this->belongsToMany(User::class, 'event_user')
            ->using(EventParticipant::class)
            ->withPivot([
                'role',
                'status',
                'responded_at',
                'attended_at',
                'certificate_issued_at',
                'certificate_expires_at',
                'notes',
            ])
            ->withTimestamps();
    }

    /** @return HasMany<EventReminder, $this> */
    public function reminders(): HasMany {
        return $this->hasMany(EventReminder::class);
    }

    /** @param Builder<Event> $query */
    public function scopeInRange(Builder $query, Carbon $from, Carbon $to): void {
        $query->where('started_at', '<', $to)
            ->where('ended_at', '>', $from);
    }

    /** @param Builder<Event> $query */
    public function scopeUpcoming(Builder $query): void {
        $query->where('started_at', '>=', now())
            ->whereNull('cancelled_at')
            ->orderBy('started_at');
    }

    /** @param Builder<Event> $query */
    public function scopeMandatory(Builder $query): void {
        $query->where('is_mandatory', true);
    }

    /** @param Builder<Event> $query */
    public function scopeForUser(Builder $query, User $user): void {
        $userId = $user->getKey();

        $query->where(function (Builder $q) use ($userId): void {
            $q->where('responsible_user_id', $userId)
                ->orWhereHas('participants', function (Builder $sub) use ($userId): void {
                    $sub->where('users.id', $userId);
                });
        });
    }

    public function isCancelled(): bool {
        return $this->cancelled_at !== null;
    }
}
