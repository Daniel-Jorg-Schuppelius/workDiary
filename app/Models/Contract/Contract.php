<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Contract.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contract;

use App\Enums\Contract\{ContractKind, ContractPartnerType, ContractStatus, ContractTermKind, IndexationMethod};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\{Customer, Document, Supplier, User};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Allgemeiner Vertrag (Welle D, Contract-Lifecycle-Management). Verträge
 * beliebiger Art (Miete, Wartung, Lizenz, Dienstleistung, Versicherung, …)
 * mit Laufzeit-/Verlängerungslogik, Kündigungsfrist, Indexierungsregel und
 * Obligationen-/Vertragskalender. Die Kündigungstermin-/Verlängerungs-
 * berechnung liegt im {@see \App\Services\Contract\ContractService}.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $number
 * @property string $title
 * @property ContractKind $kind
 * @property ContractStatus $status
 * @property ContractPartnerType $partner_type
 * @property ContractTermKind $term_kind
 * @property \Illuminate\Support\Carbon $starts_on
 * @property \Illuminate\Support\Carbon|null $ends_on
 * @property int|null $min_term_months
 * @property bool $auto_renew
 * @property int|null $renew_period_months
 * @property int|null $notice_period_days
 * @property IndexationMethod $indexation_method
 * @property CurrencyCode $currency
 */
class Contract extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'number', 'title', 'kind', 'status',
        'partner_type', 'customer_id', 'supplier_id', 'partner_name',
        'term_kind', 'starts_on', 'ends_on', 'min_term_months', 'auto_renew',
        'renew_period_months', 'notice_period_days',
        'indexation_method', 'indexation_value', 'indexation_review_on', 'indexation_note',
        'value_amount', 'currency', 'value_period', 'document_id',
        'responsible_user_id', 'notes', 'created_by', 'closed_at', 'closed_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => ContractKind::class,
        'status' => ContractStatus::class,
        'partner_type' => ContractPartnerType::class,
        'term_kind' => ContractTermKind::class,
        'indexation_method' => IndexationMethod::class,
        'currency' => CurrencyCode::class,
        'starts_on' => 'date',
        'ends_on' => 'date',
        'indexation_review_on' => 'date',
        'min_term_months' => 'integer',
        'renew_period_months' => 'integer',
        'notice_period_days' => 'integer',
        'auto_renew' => 'boolean',
        'value_amount' => 'decimal:2',
        'indexation_value' => 'decimal:4',
        'closed_at' => 'datetime',
    ];

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void {
        $query->whereIn('status', [
            ContractStatus::Draft->value,
            ContractStatus::Active->value,
            ContractStatus::Terminated->value,
        ]);
    }

    /** Angezeigter Vertragspartner: verknüpfter Kunde/Lieferant oder Freitext. */
    public function partnerLabel(): string {
        return match ($this->partner_type) {
            ContractPartnerType::Customer => (string) (optional($this->customer)->name ?? $this->partner_name ?? ''),
            ContractPartnerType::Supplier => (string) (optional($this->supplier)->name ?? $this->partner_name ?? ''),
            ContractPartnerType::Other => (string) ($this->partner_name ?? ''),
        };
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return HasMany<ContractObligation, $this> */
    public function obligations(): HasMany {
        return $this->hasMany(ContractObligation::class)->orderBy('due_on');
    }
}
