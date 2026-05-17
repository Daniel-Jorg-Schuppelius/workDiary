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
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'legacy_id',
        'user_id',
        'project_id',
        'on_call_shift_id',
        'emergency_assignment_id',
        'content',
        'response',
        'status',
        'start_at',
        'end_at',
        'is_archived',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'archived_at' => 'datetime',
            'status' => 'integer',
            'is_archived' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderBy('created_at');
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
