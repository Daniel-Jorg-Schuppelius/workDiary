<?php

/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use Database\Factories\DiaryEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DiaryEntry extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;

    /** @use HasFactory<DiaryEntryFactory> */
    use HasFactory;

    use HasTags;

    public const STATUS_DONE = -1;

    public const STATUS_IN_PROGRESS = 1;

    public const STATUS_OPEN = 2;

    public const STATUS_PROBLEM = 3;

    /** @var list<int> */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
        self::STATUS_PROBLEM,
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    /** @var list<string> */
    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    protected $fillable = [
        'organization_id',
        'entry_type_id',
        'legacy_id',
        'user_id',
        'assigned_user_id',
        'project_id',
        'customer_id',
        'on_call_shift_id',
        'emergency_assignment_id',
        'title',
        'content',
        'response',
        'status',
        'priority',
        'start_at',
        'end_at',
        'scheduled_for',
        'time_window_start',
        'time_window_end',
        'service_minutes',
        'address_line',
        'address_zip',
        'address_city',
        'address_country',
        'address_lat',
        'address_lng',
        'tour_id',
        'tour_position',
        'notes',
        'is_archived',
        'archived_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'archived_at' => 'datetime',
        'scheduled_for' => 'date',
        'status' => 'integer',
        'is_archived' => 'boolean',
        'service_minutes' => 'integer',
        'tour_position' => 'integer',
        'address_lat' => 'decimal:7',
        'address_lng' => 'decimal:7',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<EntryType, $this> */
    public function entryType(): BelongsTo
    {
        return $this->belongsTo(EntryType::class);
    }

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function hasCoordinates(): bool
    {
        return $this->address_lat !== null && $this->address_lng !== null;
    }

    /** @return BelongsTo<OnCallShift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(OnCallShift::class, 'on_call_shift_id');
    }

    /** @return BelongsTo<EmergencyAssignment, $this> */
    public function emergency(): BelongsTo
    {
        return $this->belongsTo(EmergencyAssignment::class, 'emergency_assignment_id');
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        /** @var MorphMany<Comment, $this> $relation */
        $relation = $this->morphMany(Comment::class, 'commentable')->orderBy('created_at');

        return $relation;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            -1 => __('Erledigt'),
            1 => __('Bestätigt'),
            2 => __('Offen'),
            3 => __('Problem'),
            default => __('Unbekannt'),
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            -1 => 'done',
            1 => 'progress',
            2 => 'open',
            3 => 'alert',
            default => 'neutral',
        };
    }

    /** @param Builder<DiaryEntry> $query */
    public function scopeNotArchived(Builder $query): void
    {
        $query->where('is_archived', false);
    }

    /** Offene und problematische Einträge (Status 2 = Offen, 3 = Problem).
     *
     * @param  Builder<DiaryEntry>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [2, 3]);
    }

    /** Bestätigte Einträge (Status 1 = In Bearbeitung).
     *
     * @param  Builder<DiaryEntry>  $query
     */
    public function scopeInProgress(Builder $query): void
    {
        $query->where('status', 1);
    }
}
