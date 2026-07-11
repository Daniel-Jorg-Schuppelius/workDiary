<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimCase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Claims;

use App\Enums\Claims\{ClaimSource, ClaimStatus};
use App\Models\{Article, Asset, Classification, Customer, DiaryEntry, Invoice, Project, Protocol, PurchaseOrder, ServiceTicket, StockLot, StockSerial, Supplier, User};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Reklamationsakte (Feature 072, MVP-247): Fallakte mit Status, Fristen,
 * Nachweisen, Bewertung, Entscheidung und Folgen. Betroffene Fachobjekte
 * (Auftrag/Asset/Artikel/Rechnung/Lieferant) bleiben in ihren Modulen
 * führend — hier wird nur verknüpft, nie überschrieben.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $number
 * @property ClaimStatus $status
 * @property ClaimSource $source
 * @property string $priority
 * @property string $severity
 * @property string $title
 * @property string|null $description
 * @property int|null $customer_id
 * @property string|null $reporter_name
 * @property string|null $reporter_email
 * @property bool $is_b2b
 * @property \Illuminate\Support\Carbon $reported_at
 * @property \Illuminate\Support\Carbon|null $complaint_notice_at
 * @property \Illuminate\Support\Carbon|null $due_at
 * @property int|null $responsible_user_id
 * @property string|null $serial_no
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property \Illuminate\Support\Carbon|null $anonymized_at
 */
class ClaimCase extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const SEVERITIES = ['minor', 'major', 'critical'];

    protected $fillable = [
        'organization_id', 'number', 'status', 'source', 'priority', 'severity',
        'title', 'description', 'customer_id', 'reporter_name', 'reporter_email',
        'is_b2b', 'reported_at', 'complaint_notice_at', 'due_at',
        'responsible_user_id', 'diary_entry_id', 'project_id', 'service_ticket_id',
        'protocol_id', 'asset_id', 'article_id', 'invoice_id', 'supplier_id',
        'purchase_order_id', 'stock_serial_id', 'stock_lot_id', 'serial_no',
        'defect_type_classification_id', 'root_cause_classification_id',
        'goodwill_reason_classification_id', 'decided_at', 'closed_at',
        'closed_by', 'created_by', 'anonymized_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => ClaimStatus::class,
        'source' => ClaimSource::class,
        'is_b2b' => 'boolean',
        'reported_at' => 'datetime',
        'complaint_notice_at' => 'date',
        'due_at' => 'datetime',
        'decided_at' => 'datetime',
        'closed_at' => 'datetime',
        'anonymized_at' => 'datetime',
    ];

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void {
        $query->whereIn('status', [ClaimStatus::Received->value, ClaimStatus::Assessing->value, ClaimStatus::Decided->value, ClaimStatus::InProgress->value]);
    }

    /** @param Builder<self> $query */
    public function scopeOverdue(Builder $query): void {
        $query->open()->whereNotNull('due_at')->where('due_at', '<', now());
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ServiceTicket, $this> */
    public function serviceTicket(): BelongsTo {
        return $this->belongsTo(ServiceTicket::class);
    }

    /** @return BelongsTo<Protocol, $this> */
    public function protocol(): BelongsTo {
        return $this->belongsTo(Protocol::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<StockSerial, $this> */
    public function stockSerial(): BelongsTo {
        return $this->belongsTo(StockSerial::class);
    }

    /** @return BelongsTo<StockLot, $this> */
    public function stockLot(): BelongsTo {
        return $this->belongsTo(StockLot::class);
    }

    /** @return BelongsTo<Classification, $this> */
    public function defectType(): BelongsTo {
        return $this->belongsTo(Classification::class, 'defect_type_classification_id');
    }

    /** @return BelongsTo<Classification, $this> */
    public function rootCause(): BelongsTo {
        return $this->belongsTo(Classification::class, 'root_cause_classification_id');
    }

    /** @return BelongsTo<Classification, $this> */
    public function goodwillReason(): BelongsTo {
        return $this->belongsTo(Classification::class, 'goodwill_reason_classification_id');
    }

    /** @return HasMany<ClaimCaseLink, $this> */
    public function links(): HasMany {
        return $this->hasMany(ClaimCaseLink::class, 'claim_case_id');
    }

    /** @return HasMany<ClaimEvidence, $this> */
    public function evidence(): HasMany {
        return $this->hasMany(ClaimEvidence::class, 'claim_case_id');
    }

    /** @return HasMany<ClaimAssessment, $this> */
    public function assessments(): HasMany {
        return $this->hasMany(ClaimAssessment::class, 'claim_case_id');
    }

    /** @return HasMany<ClaimDecision, $this> */
    public function decisions(): HasMany {
        return $this->hasMany(ClaimDecision::class, 'claim_case_id');
    }

    /** @return HasMany<ClaimRmaReturn, $this> */
    public function rmaReturns(): HasMany {
        return $this->hasMany(ClaimRmaReturn::class, 'claim_case_id');
    }

    /** @return HasMany<ClaimAction, $this> */
    public function actions(): HasMany {
        return $this->hasMany(ClaimAction::class, 'claim_case_id');
    }

    /** @return HasMany<ClaimFinancialOutcome, $this> */
    public function financialOutcomes(): HasMany {
        return $this->hasMany(ClaimFinancialOutcome::class, 'claim_case_id');
    }

    /** @return HasMany<ClaimSupplierRecourse, $this> */
    public function supplierRecourses(): HasMany {
        return $this->hasMany(ClaimSupplierRecourse::class, 'claim_case_id');
    }

    public function activeAssessment(): ?ClaimAssessment {
        return $this->assessments->firstWhere('status', 'active');
    }

    public function latestDecision(): ?ClaimDecision {
        return $this->decisions->sortByDesc('decided_at')->first();
    }
}
