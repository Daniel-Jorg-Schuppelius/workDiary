<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Invoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Collection, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $customer_id
 * @property int|null $project_id
 * @property int|null $foreign_customer_id
 * @property string $number
 * @property string $status
 * @property string $type
 * @property int|null $parent_invoice_id
 * @property Carbon|null $issued_on
 * @property Carbon|null $due_on
 * @property Carbon|null $paid_on
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancel_reason
 * @property Carbon|null $sent_at
 * @property int $sent_count
 * @property string $currency
 * @property string $subtotal
 * @property string $tax_rate
 * @property string $tax_amount
 * @property string $total
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InvoiceItem> $items
 * @property-read Customer $customer
 * @property-read Project|null $project
 * @property-read Invoice|null $parent
 * @property-read Collection<int, Invoice> $creditNotes
 */
class Invoice extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_CREDIT_NOTE = 'credit_note';

    /** @var array<int, string> */
    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_ISSUED, self::STATUS_PAID, self::STATUS_CANCELLED];

    /** @var array<int, string> */
    public const TYPES = [self::TYPE_INVOICE, self::TYPE_CREDIT_NOTE];

    protected $fillable = [
        'organization_id',
        'customer_id',
        'project_id',
        'foreign_customer_id',
        'number',
        'status',
        'type',
        'parent_invoice_id',
        'issued_on',
        'due_on',
        'paid_on',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'sent_at',
        'sent_count',
        'currency',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'notes',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'issued_on' => 'date',
        'due_on' => 'date',
        'paid_on' => 'date',
        'cancelled_at' => 'datetime',
        'sent_at' => 'datetime',
        'sent_count' => 'integer',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ForeignCustomer, $this> */
    public function foreignCustomer(): BelongsTo {
        return $this->belongsTo(ForeignCustomer::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function parent(): BelongsTo {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    /** @return HasMany<Invoice, $this> */
    public function creditNotes(): HasMany {
        return $this->hasMany(Invoice::class, 'parent_invoice_id')
            ->where('type', self::TYPE_CREDIT_NOTE);
    }

    public function recalculate(): void {
        $sub = 0.0;
        foreach ($this->items as $item) {
            $sub += (float) $item->amount;
        }
        $tax = round($sub * ((float) $this->tax_rate) / 100, 2);
        $this->subtotal = (string) round($sub, 2);
        $this->tax_amount = (string) $tax;
        $this->total = (string) round($sub + $tax, 2);
    }

    public function isCreditNote(): bool {
        return $this->type === self::TYPE_CREDIT_NOTE;
    }

    public function isCancelled(): bool {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Storno ist möglich, solange die Rechnung NICHT bezahlt wurde.
     * Für bezahlte Rechnungen muss stattdessen eine Gutschrift (Korrekturrechnung)
     * erstellt werden — vgl. {@see needsCreditNoteToCancel()}.
     */
    public function canBeCancelled(): bool {
        if ($this->isCancelled() || $this->isCreditNote()) {
            return false;
        }

        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_ISSUED], true);
    }

    /**
     * Bezahlte Rechnungen dürfen nur per Gutschrift (Korrekturrechnung) storniert werden.
     * Pro Original existiert höchstens eine aktive Gutschrift.
     */
    public function needsCreditNoteToCancel(): bool {
        return $this->status === self::STATUS_PAID
            && ! $this->isCreditNote()
            && $this->creditNotes()->count() === 0;
    }

    /**
     * Storniert die Rechnung (nur draft/issued). Bei bezahlten Rechnungen wirft
     * dies eine LogicException — der Aufrufer muss createCreditNote() verwenden.
     *
     * @throws \LogicException
     */
    public function cancel(?string $reason, ?int $userId): void {
        if (! $this->canBeCancelled()) {
            throw new \LogicException('Invoice cannot be cancelled in current state: ' . $this->status);
        }
        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = now();
        $this->cancelled_by = $userId;
        $this->cancel_reason = $reason;
        $this->save();
    }

    public function markSent(): void {
        $this->sent_at = now();
        $this->sent_count = ((int) $this->sent_count) + 1;
        if ($this->status === self::STATUS_DRAFT && ! $this->isCreditNote()) {
            $this->status = self::STATUS_ISSUED;
            $this->issued_on ??= now();
            $this->due_on ??= now()->addDays(14);
        }
        $this->save();
    }

    /** Display-Label für PDF/Show (deutsch). */
    public function documentLabel(): string {
        return $this->isCreditNote() ? __('Gutschrift') : __('Rechnung');
    }
}
