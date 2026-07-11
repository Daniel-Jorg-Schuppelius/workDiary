<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalCase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Rental;

use App\Enums\Rental\RentalCaseStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\{Customer, DiaryEntry, Project, Site, User};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Verleihakte (Feature 073, MVP-261): führt Kunde, Zeitraum, Konditionen
 * (Rate-Card-Snapshot, D10), Kaution, Übergabe/Rücknahme und kaufmännische
 * Folge. Das Asset bleibt im Asset-Modul führend.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $number
 * @property RentalCaseStatus $status
 * @property int $customer_id
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property \Illuminate\Support\Carbon|null $actual_return_at
 * @property array<string, mixed>|null $terms_snapshot
 * @property numeric-string|null $deposit_amount
 */
class RentalCase extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'number', 'status', 'customer_id', 'contact_name',
        'project_id', 'diary_entry_id', 'site_id', 'handover_location',
        'return_location', 'starts_at', 'ends_at', 'actual_return_at',
        'responsible_user_id', 'rental_rate_card_id', 'terms_snapshot',
        'deposit_amount', 'insurance_note', 'notes', 'created_by',
        'closed_at', 'closed_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => RentalCaseStatus::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'actual_return_at' => 'datetime',
        'terms_snapshot' => 'array',
        'deposit_amount' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void {
        $query->whereIn('status', [
            RentalCaseStatus::Draft->value,
            RentalCaseStatus::Reserved->value,
            RentalCaseStatus::HandedOver->value,
            RentalCaseStatus::Overdue->value,
        ]);
    }

    /** @param Builder<self> $query */
    public function scopeOverdueCandidates(Builder $query): void {
        $query->where('status', RentalCaseStatus::HandedOver->value)
            ->whereNull('actual_return_at')
            ->where('ends_at', '<', now());
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return BelongsTo<RentalRateCard, $this> */
    public function rateCard(): BelongsTo {
        return $this->belongsTo(RentalRateCard::class, 'rental_rate_card_id');
    }

    /** @return HasMany<RentalCaseAsset, $this> */
    public function caseAssets(): HasMany {
        return $this->hasMany(RentalCaseAsset::class);
    }

    /** @return HasMany<RentalReservation, $this> */
    public function reservations(): HasMany {
        return $this->hasMany(RentalReservation::class);
    }

    /** @return HasMany<RentalHandoverReport, $this> */
    public function handoverReports(): HasMany {
        return $this->hasMany(RentalHandoverReport::class);
    }

    /** @return HasMany<RentalReturnReport, $this> */
    public function returnReports(): HasMany {
        return $this->hasMany(RentalReturnReport::class);
    }

    /** @return HasMany<RentalCharge, $this> */
    public function charges(): HasMany {
        return $this->hasMany(RentalCharge::class);
    }

    /** @return HasMany<RentalDeposit, $this> */
    public function deposits(): HasMany {
        return $this->hasMany(RentalDeposit::class);
    }

    public function isOverdue(): bool {
        return $this->status === RentalCaseStatus::Overdue
            || ($this->status === RentalCaseStatus::HandedOver
                && $this->actual_return_at === null
                && $this->ends_at->isPast());
    }
}
