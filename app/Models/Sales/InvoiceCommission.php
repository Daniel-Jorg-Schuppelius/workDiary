<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceCommission.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sales;

use App\Casts\{MoneyCast, PercentageCast};
use App\Enums\Sales\{CommissionAssignmentSource, CommissionStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Invoice, Lead, User};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\{Money, Percentage};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eine Provisionszeile: Beleg × Vertriebsperson (Feature 146, MVP-729).
 *
 * Entsteht **nur** an der bezahlten Rechnung (Auslöser: Statuswechsel auf
 * `paid`, dieselbe Naht wie der `invoice.paid`-Lifecycle-Webhook aus MVP-718).
 * Satz und Bemessungsgrundlage sind eingefroren — die Regel darf sich danach
 * beliebig aendern.
 *
 * Eine Rueckrechnung ist eine eigene Zeile mit negativen Betraegen und
 * `reversal_of_id` auf die Ursprungszeile. Das ist der einzige Weg, eine
 * bereits festgeschriebene Provision zu korrigieren.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $invoice_id
 * @property int $user_id
 * @property int|null $commission_rule_id
 * @property CommissionAssignmentSource $assignment_source
 * @property int|null $lead_id
 * @property CurrencyCode $currency
 * @property Money $base_amount
 * @property Percentage $rate_percent
 * @property Money $commission_amount
 * @property Carbon $earned_on
 * @property CommissionStatus $status
 * @property int|null $settlement_run_id
 * @property int|null $reversal_of_id
 * @property string|null $note
 */
class InvoiceCommission extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'invoice_commissions';

    protected $fillable = [
        'organization_id',
        'invoice_id',
        'user_id',
        'commission_rule_id',
        'assignment_source',
        'lead_id',
        'currency',
        'base_amount',
        'rate_percent',
        'commission_amount',
        'earned_on',
        'status',
        'settlement_run_id',
        'reversal_of_id',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'assignment_source' => CommissionAssignmentSource::class,
        'currency' => CurrencyCode::class,
        'base_amount' => MoneyCast::class,
        'rate_percent' => PercentageCast::class . ':2',
        'commission_amount' => MoneyCast::class,
        'earned_on' => 'date',
        'status' => CommissionStatus::class,
    ];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<CommissionRule, $this> */
    public function rule(): BelongsTo {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /** @return BelongsTo<CommissionSettlementRun, $this> */
    public function settlementRun(): BelongsTo {
        return $this->belongsTo(CommissionSettlementRun::class, 'settlement_run_id');
    }

    /** @return BelongsTo<InvoiceCommission, $this> */
    public function reversalOf(): BelongsTo {
        return $this->belongsTo(InvoiceCommission::class, 'reversal_of_id');
    }

    public function isReversal(): bool {
        return $this->reversal_of_id !== null;
    }

    /**
     * Noch nicht abgerechnete Zeilen (Vorschau eines Laufs).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder {
        return $query->where('status', CommissionStatus::Pending->value)->whereNull('settlement_run_id');
    }
}
