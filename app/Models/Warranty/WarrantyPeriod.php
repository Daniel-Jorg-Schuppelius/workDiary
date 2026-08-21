<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarrantyPeriod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Warranty;

use App\Enums\Warranty\{WarrantyBasis, WarrantySide, WarrantyStatus};
use App\Models\Claims\ClaimCase;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Customer, DiaryEntry, Project, Protocol, Supplier, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Gewährleistungsfrist (Feature 115, MVP-604).
 *
 * @property WarrantySide $side
 * @property WarrantyBasis $basis
 * @property WarrantyStatus $status
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
class WarrantyPeriod extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'side',
        'basis',
        'starts_on',
        'ends_on',
        'override_reason',
        'protocol_id',
        'project_id',
        'diary_entry_id',
        'customer_id',
        'supplier_id',
        'trade',
        'status',
        'claim_case_id',
        'responsible_user_id',
        'note',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'side' => WarrantySide::class,
        'basis' => WarrantyBasis::class,
        'status' => WarrantyStatus::class,
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'open'];

    /** @return BelongsTo<Protocol, $this> */
    public function protocol(): BelongsTo {
        return $this->belongsTo(Protocol::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<ClaimCase, $this> */
    public function claimCase(): BelongsTo {
        return $this->belongsTo(ClaimCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** Gegenpartei-Anzeige: Kunde bei eigener Haftung, Lieferant bei einforderbarer. */
    public function partyLabel(): string {
        return $this->side === WarrantySide::Owed
            ? ($this->customer?->displayLabel() ?? '—')
            : ($this->supplier?->displayLabel() ?? '—');
    }

    /** Weicht das Enddatum von der Regel-Laufzeit ab? */
    public function isOverridden(): bool {
        $months = $this->basis->months();

        return $months !== null && ! $this->ends_on->equalTo($this->starts_on->copy()->addMonths($months));
    }

    public function isRunning(): bool {
        return $this->status === WarrantyStatus::Open && ! $this->ends_on->isPast();
    }
}
