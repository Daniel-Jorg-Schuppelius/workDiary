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

use App\Enums\Diary\{LocationMode, Mode, Priority, Status};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasTags};
use Database\Factories\DiaryEntryFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $entry_type_id
 * @property int|null $legacy_id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int|null $project_id
 * @property int|null $customer_id
 * @property int|null $asset_id
 * @property int|null $on_call_shift_id
 * @property int|null $emergency_assignment_id
 * @property string|null $title
 * @property string|null $content
 * @property string|null $response
 * @property Status $status
 * @property Priority|null $priority
 * @property Carbon|null $start_at
 * @property Carbon|null $end_at
 * @property Carbon|null $scheduled_for
 * @property string|null $time_window_start
 * @property string|null $time_window_end
 * @property int|null $service_minutes
 * @property int|null $planned_minutes
 * @property Carbon|null $planned_at
 * @property int|null $planned_by_user_id
 * @property string|null $address_line
 * @property string|null $address_zip
 * @property string|null $address_city
 * @property string|null $address_country
 * @property string|null $address_lat
 * @property string|null $address_lng
 * @property int|null $tour_id
 * @property int|null $tour_position
 * @property string|null $notes
 * @property Mode $mode
 * @property Carbon|null $due_date
 * @property Carbon|null $window_start_date
 * @property Carbon|null $window_end_date
 * @property LocationMode $location_mode
 * @property int|null $recurrence_rule_id
 * @property bool $is_archived
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DiaryEntry extends Model {
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
        'asset_id',
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
        'planned_minutes',
        'planned_at',
        'planned_by_user_id',
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
        'planned_minutes' => 'integer',
        'planned_at' => 'datetime',
        'tour_position' => 'integer',
        'address_lat' => 'decimal:7',
        'address_lng' => 'decimal:7',
        'priority' => Priority::class,
        'location_mode' => LocationMode::class,
        'mode' => Mode::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<EntryType, $this> */
    public function entryType(): BelongsTo {
        return $this->belongsTo(EntryType::class);
    }

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo {
        return $this->belongsTo(Tour::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany {
        return $this->hasMany(TimeEntry::class);
    }

    /** @return BelongsTo<RecurrenceRule, $this> */
    public function recurrenceRule(): BelongsTo {
        return $this->belongsTo(RecurrenceRule::class);
    }

    public function hasCoordinates(): bool {
        return $this->address_lat !== null && $this->address_lng !== null;
    }

    /** @return BelongsTo<OnCallShift, $this> */
    public function shift(): BelongsTo {
        return $this->belongsTo(OnCallShift::class, 'on_call_shift_id');
    }

    /** @return BelongsTo<EmergencyAssignment, $this> */
    public function emergency(): BelongsTo {
        return $this->belongsTo(EmergencyAssignment::class, 'emergency_assignment_id');
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany {
        /** @var MorphMany<Comment, $this> $relation */
        $relation = $this->morphMany(Comment::class, 'commentable')->orderBy('created_at');

        return $relation;
    }

    /** @return MorphMany<OpenIssue, $this> */
    public function openIssues(): MorphMany {
        /** @var MorphMany<OpenIssue, $this> $relation */
        $relation = $this->morphMany(OpenIssue::class, 'subject')->latest('id');

        return $relation;
    }

    public function statusLabel(): string {
        return $this->status->label();
    }

    public function statusTone(): string {
        return $this->status->tone();
    }

    public function modeLabel(): string {
        return $this->mode->label();
    }

    public function locationLabel(): string {
        return $this->location_mode->label();
    }

    /** @param Builder<DiaryEntry> $query */
    public function scopeNotArchived(Builder $query): void {
        $query->where('is_archived', false);
    }

    /** Offene und problematische Einträge (Status 2 = Offen, 3 = Problem).
     *
     * @param  Builder<DiaryEntry>  $query
     */
    public function scopeOpen(Builder $query): void {
        $query->whereIn('status', [Status::Open->value, Status::Problem->value]);
    }

    /** Bestätigte Einträge (Status 1 = In Bearbeitung).
     *
     * @param  Builder<DiaryEntry>  $query
     */
    public function scopeInProgress(Builder $query): void {
        $query->where('status', Status::InProgress->value);
    }
}
