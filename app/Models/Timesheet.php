<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Timesheet.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAttachments;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $project_id
 * @property int $user_id
 * @property string $kind
 * @property Carbon $work_date
 * @property string $status
 * @property string|null $customer_name
 * @property string|null $customer_role
 * @property string|null $customer_email
 * @property Carbon|null $signed_at
 * @property string|null $signed_ip
 * @property int|null $signature_attachment_id
 * @property string|null $signature_hash
 * @property Carbon|null $locked_at
 * @property int|null $locked_by
 * @property string|null $notes
 * @property int $totals_minutes
 * @property int $attendance_total_minutes
 * @property int $entries_total_minutes
 * @property int $untracked_minutes
 * @property string $totals_material_net
 * @property string|null $magic_token
 * @property Carbon|null $magic_expires_at
 */
class Timesheet extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = []) {
        parent::__construct($attributes);
    }

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_LOCKED = 'locked';

    /** @var array<int, string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_SIGNED,
        self::STATUS_LOCKED,
    ];

    public const KIND_PROJECT = 'project';

    public const KIND_PERSONAL_DAY = 'personal_day';

    /** @var array<int, string> */
    public const KINDS = [self::KIND_PROJECT, self::KIND_PERSONAL_DAY];

    protected $fillable = [
        'organization_id',
        'project_id',
        'user_id',
        'kind',
        'work_date',
        'status',
        'customer_name',
        'customer_role',
        'customer_email',
        'signed_at',
        'signed_ip',
        'signature_attachment_id',
        'signature_hash',
        'locked_at',
        'locked_by',
        'notes',
        'totals_minutes',
        'attendance_total_minutes',
        'entries_total_minutes',
        'untracked_minutes',
        'totals_material_net',
        'magic_token',
        'magic_expires_at',
    ];

    protected function casts(): array {
        return [
            'work_date' => 'date',
            'signed_at' => 'datetime',
            'locked_at' => 'datetime',
            'magic_expires_at' => 'datetime',
            'totals_minutes' => 'integer',
            'attendance_total_minutes' => 'integer',
            'entries_total_minutes' => 'integer',
            'untracked_minutes' => 'integer',
            'totals_material_net' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function locker(): BelongsTo {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /** @return BelongsTo<Attachment, $this> */
    public function signatureAttachment(): BelongsTo {
        return $this->belongsTo(Attachment::class, 'signature_attachment_id');
    }

    /** @return HasMany<TimeEntry, $this> */
    public function entries(): HasMany {
        return $this->hasMany(TimeEntry::class)->orderBy('started_at')->orderBy('id');
    }

    /** @return HasMany<MaterialUsage, $this> */
    public function materialUsages(): HasMany {
        return $this->hasMany(MaterialUsage::class)->orderBy('id');
    }

    /**
     * @param  Builder<Timesheet>  $q
     * @return Builder<Timesheet>
     */
    public function scopeForUser(Builder $q, int $userId): Builder {
        return $q->where('user_id', $userId);
    }

    /**
     * @param  Builder<Timesheet>  $q
     * @return Builder<Timesheet>
     */
    public function scopeInRange(Builder $q, CarbonInterface $from, CarbonInterface $to): Builder {
        return $q->whereBetween('work_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * @param  Builder<Timesheet>  $q
     * @return Builder<Timesheet>
     */
    public function scopeUnsigned(Builder $q): Builder {
        return $q->whereIn('status', [self::STATUS_DRAFT, self::STATUS_SUBMITTED]);
    }

    public function isSigned(): bool {
        return in_array($this->status, [self::STATUS_SIGNED, self::STATUS_LOCKED], true);
    }

    public function isLocked(): bool {
        return $this->status === self::STATUS_LOCKED;
    }

    public function canEdit(): bool {
        return ! $this->isSigned();
    }

    public function recalcTotals(): void {
        $this->loadMissing(['entries', 'materialUsages']);
        $minutes = (int) $this->entries->sum('minutes');
        $material = (float) $this->materialUsages->sum('line_total_net');
        $this->totals_minutes = $minutes;
        $this->entries_total_minutes = $minutes;
        $this->untracked_minutes = max(0, (int) $this->attendance_total_minutes - $minutes);
        $this->totals_material_net = (string) round($material, 2);
        $this->saveQuietly();
    }

    /**
     * Backward-compatible Alias für bestehende Views/Exports.
     *
     * Historisch wurde in Blade/PDF `total_work_minutes` verwendet, im Modell
     * wird jedoch `totals_minutes`/`entries_total_minutes` persistiert.
     */
    public function getTotalWorkMinutesAttribute(): int {
        if ($this->relationLoaded('entries')) {
            return (int) $this->entries->sum('minutes');
        }

        return (int) $this->entries_total_minutes;
    }

    /**
     * Dynamische Pausen-Summe über die Zeiteinträge.
     */
    public function getTotalBreakMinutesAttribute(): int {
        if ($this->relationLoaded('entries')) {
            return (int) $this->entries->sum('break_minutes');
        }

        return (int) $this->entries()->sum('break_minutes');
    }

    /**
     * Backward-compatible Alias für `totals_material_net`.
     */
    public function getTotalMaterialNetAttribute(): float {
        if ($this->relationLoaded('materialUsages')) {
            return (float) $this->materialUsages->sum('line_total_net');
        }

        return (float) $this->totals_material_net;
    }

    public function isPersonalDay(): bool {
        return $this->kind === self::KIND_PERSONAL_DAY;
    }

    public function statusLabel(): string {
        return match ($this->status) {
            self::STATUS_DRAFT => __('Entwurf'),
            self::STATUS_SUBMITTED => __('Eingereicht'),
            self::STATUS_SIGNED => $this->hasSignatureEvidence() ? __('Signiert') : __('Eingereicht'),
            self::STATUS_LOCKED => __('Gesperrt'),
            default => $this->status,
        };
    }

    public function statusTone(): string {
        return match ($this->status) {
            self::STATUS_DRAFT => 'neutral',
            self::STATUS_SUBMITTED => 'info',
            self::STATUS_SIGNED => $this->hasSignatureEvidence() ? 'success' : 'info',
            self::STATUS_LOCKED => 'warning',
            default => 'ghost',
        };
    }

    /**
     * Für die Anzeige gilt ein Stundenzettel nur dann als signiert,
     * wenn nachvollziehbare Signaturdaten vorhanden sind.
     */
    public function hasSignatureEvidence(): bool {
        return $this->signed_at !== null
            && ($this->signature_attachment_id !== null || ! empty($this->signature_hash));
    }
}
