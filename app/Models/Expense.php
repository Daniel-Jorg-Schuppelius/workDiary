<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Expense.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\{MoneyCast, PercentageCast};
use App\Enums\Expense\{ExpenseStatus, PaymentMethod};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\{Money, Percentage};
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $user_id
 * @property int|null $expense_category_id
 * @property int|null $project_id
 * @property int|null $customer_id
 * @property int|null $task_id
 * @property int|null $attendance_id
 * @property Carbon $date
 * @property string|null $vendor
 * @property string $description
 * @property PaymentMethod $payment_method
 * @property \CommonToolkit\Enums\CurrencyCode $currency
 * @property \CommonToolkit\ValueObjects\Money|null $amount_net
 * @property \CommonToolkit\ValueObjects\Percentage|null $tax_rate
 * @property \CommonToolkit\ValueObjects\Money|null $tax_amount
 * @property \CommonToolkit\ValueObjects\Money|null $amount_gross
 * @property bool $billable
 * @property ExpenseStatus $status
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $reject_reason
 * @property Carbon|null $reimbursed_at
 * @property string|null $reimbursement_reference
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Expense extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasAttachments;
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'user_id',
        'expense_category_id',
        'project_id',
        'customer_id',
        'task_id',
        'attendance_id',
        'date',
        'vendor',
        'description',
        'payment_method',
        'currency',
        'amount_net',
        'tax_rate',
        'tax_amount',
        'amount_gross',
        'billable',
        'status',
        'decided_by',
        'decided_at',
        'reject_reason',
        'reimbursed_at',
        'reimbursement_reference',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'date' => 'date',
        'amount_net' => MoneyCast::class . ':currency,2',
        'tax_rate' => PercentageCast::class . ':2',
        'tax_amount' => MoneyCast::class . ':currency,2',
        'amount_gross' => MoneyCast::class . ':currency,2',
        'billable' => 'boolean',
        'decided_at' => 'datetime',
        'reimbursed_at' => 'datetime',
        'status' => ExpenseStatus::class,
        'payment_method' => PaymentMethod::class,
    ];

    protected static function booted(): void {
        static::saving(function (self $expense): void {
            $expense->recalculateAmounts();
        });
    }

    /**
     * Berechnet tax_amount und amount_gross aus amount_net und tax_rate.
     * Falls nur Brutto erfasst wurde (Netto = 0, Brutto > 0), wird
     * Netto aus Brutto rückgerechnet.
     */
    public function recalculateAmounts(): void {
        $currency = $this->currency ?? CurrencyCode::Euro;
        $zero = Money::zero($currency);
        $net = $this->amount_net ?? $zero;
        $rate = $this->tax_rate ?? Percentage::of('0');
        $gross = $this->amount_gross ?? $zero;

        // Nur brutto erfasst: Netto herausrechnen (Bruttobetrag ÷ (1 + Satz)).
        if (!$net->isPositive() && $gross->isPositive()) {
            $net = $rate->isPositive()
                ? $gross->dividedBy(1 + (float) $rate->getNumericValue() / 100)
                : $gross;
            $this->amount_net = $net;
        }

        $tax = $rate->amountOf($net);
        $this->tax_amount = $tax;
        $this->amount_gross = $net->plus($tax);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<Attendance, $this> */
    public function attendance(): BelongsTo {
        return $this->belongsTo(Attendance::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<InvoiceItem, $this> */
    public function invoiceItem(): \Illuminate\Database\Eloquent\Relations\HasOne {
        return $this->hasOne(InvoiceItem::class);
    }

    /**
     * @param  Builder<Expense>  $query
     * @return Builder<Expense>
     */
    public function scopeForUser(Builder $query, int $userId): Builder {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<Expense>  $query
     * @return Builder<Expense>
     */
    public function scopeWithStatus(Builder $query, ExpenseStatus|string $status): Builder {
        $value = $status instanceof ExpenseStatus ? $status->value : $status;

        return $query->where('status', $value);
    }
}
