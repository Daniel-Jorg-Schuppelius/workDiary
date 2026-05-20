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

use App\Enums\Diary\LocationMode;
use App\Enums\Diary\Mode;
use App\Enums\Diary\Priority;
use App\Enums\Diary\Status;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use Database\Factories\DiaryEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DiaryEntry extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;

    /** @use HasFactory<DiaryEntryFactory> */
    use HasFactory;

    use HasTags;

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
        'mode',
        'due_date',
        'window_start_date',
        'window_end_date',
        'location_mode',
        'recurrence_rule_id',
        'is_archived',
        'archived_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'archived_at' => 'datetime',
        'scheduled_for' => 'date',
        'due_date' => 'date',
        'window_start_date' => 'date',
        'window_end_date' => 'date',
        'status' => Status::class,
        'is_archived' => 'boolean',
        'service_minutes' => 'integer',
        'tour_position' => 'integer',
        'address_lat' => 'decimal:7',
        'address_lng' => 'decimal:7',
        'priority' => Priority::class,
        'location_mode' => LocationMode::class,
        'mode' => Mode::class,
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

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** @return BelongsTo<RecurrenceRule, $this> */
    public function recurrenceRule(): BelongsTo
    {
        return $this->belongsTo(RecurrenceRule::class);
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
        return $this->status->label();
    }

    public function statusTone(): string
    {
        return $this->status->tone();
    }

    public function modeLabel(): string
    {
        return $this->mode->label();
    }

    public function locationLabel(): string
    {
        return $this->location_mode->label();
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
        $query->whereIn('status', [Status::Open->value, Status::Problem->value]);
    }

    /** Bestätigte Einträge (Status 1 = In Bearbeitung).
     *
     * @param  Builder<DiaryEntry>  $query
     */
    public function scopeInProgress(Builder $query): void
    {
        $query->where('status', Status::InProgress->value);
    }
}
