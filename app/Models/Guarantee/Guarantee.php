<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Guarantee.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Guarantee;

use App\Casts\MoneyCast;
use App\Enums\Guarantee\{GuaranteeDirection, GuaranteeKind, GuaranteeStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\Contract\Contract;
use App\Models\{Customer, Project, Supplier, User};
use App\Models\Invoicing\InvoiceRetention;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bürgschaft (Feature 114, MVP-603).
 *
 * Die Urkunde selbst hängt als Anhang ({@see HasAttachments}) — ohne sie ist
 * die Bürgschaft praktisch nicht durchsetzbar, und der Rückgabe-Nachweis
 * bezieht sich genau auf dieses Dokument.
 *
 * @property GuaranteeDirection $direction
 * @property GuaranteeKind $kind
 * @property GuaranteeStatus $status
 * @property Money $amount
 * @property Carbon|null $issued_on
 * @property Carbon|null $expires_on
 * @property Carbon|null $returned_on
 */
class Guarantee extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'direction',
        'kind',
        'reference',
        'amount',
        'currency',
        'issued_on',
        'expires_on',
        'issuer_name',
        'issuer_supplier_id',
        'customer_id',
        'supplier_id',
        'project_id',
        'contract_id',
        'invoice_retention_id',
        'status',
        'returned_on',
        'returned_note',
        'note',
        'responsible_user_id',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'direction' => GuaranteeDirection::class,
        'kind' => GuaranteeKind::class,
        'status' => GuaranteeStatus::class,
        'amount' => MoneyCast::class . ':currency',
        'issued_on' => 'date',
        'expires_on' => 'date',
        'returned_on' => 'date',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active', 'currency' => 'EUR'];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function issuerSupplier(): BelongsTo {
        return $this->belongsTo(Supplier::class, 'issuer_supplier_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<InvoiceRetention, $this> */
    public function retention(): BelongsTo {
        return $this->belongsTo(InvoiceRetention::class, 'invoice_retention_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** Anzeigename des Bürgen: Stammdaten-Lieferant vor Freitext. */
    public function issuerLabel(): string {
        return $this->issuerSupplier?->displayLabel() ?? (string) ($this->issuer_name ?? '—');
    }

    /** Ist die Befristung abgelaufen, ohne dass jemand reagiert hat? */
    public function isExpiredUnnoticed(): bool {
        return $this->status === GuaranteeStatus::Active
            && $this->expires_on !== null
            && $this->expires_on->isPast();
    }
}
