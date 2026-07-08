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

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
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
 * @property string $category
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
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_CREDIT_NOTE = 'credit_note';

    public const TYPE_CANCELLATION = 'cancellation';

    public const TYPE_DOWN_PAYMENT = 'down_payment';

    public const TYPE_PARTIAL = 'partial';

    public const TYPE_FINAL = 'final';

    public const TYPE_PROFORMA = 'proforma';

    public const CATEGORY_SERVICE = 'service';

    public const CATEGORY_MATERIAL = 'material';

    /** @var array<int, string> */
    public const CATEGORIES = [self::CATEGORY_SERVICE, self::CATEGORY_MATERIAL];

    /** @var array<int, string> */
    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_ISSUED, self::STATUS_PARTIALLY_PAID, self::STATUS_PAID, self::STATUS_CANCELLED];

    /** @var array<int, string> */
    public const TYPES = [self::TYPE_INVOICE, self::TYPE_CREDIT_NOTE, self::TYPE_CANCELLATION, self::TYPE_DOWN_PAYMENT, self::TYPE_PARTIAL, self::TYPE_FINAL, self::TYPE_PROFORMA];

    /**
     * Nach der Ausstellung fachlich unveränderlich (MVP-162) — nur diese
     * Felder dürfen sich über normale save()-Wege noch ändern.
     * (saveQuietly interner Abgleichspfade umgeht den Guard bewusst.)
     */
    public const MUTABLE_AFTER_ISSUE = [
        'status', 'paid_on', 'sent_at', 'sent_count',
        'cancelled_at', 'cancelled_by', 'cancel_reason',
        'external_number', 'number_source', 'updated_at',
        'dunning_level', 'dunned_at', // Mahnstatus ist Lifecycle, kein Beleginhalt
        'objection_at', 'objection_note', // Widerspruch (§ 14 Abs. 2 UStG) ist Lifecycle
    ];

    protected $fillable = [
        'organization_id',
        'customer_id',
        'project_id',
        'foreign_customer_id',
        'number',
        'status',
        'type',
        'category',
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
        'is_reverse_charge',
        'tax_amount',
        'total',
        'notes',
        'created_by',
        'party_snapshot',
        'tax_breakdown',
        'payment_terms_days',
        'approved_at',
        'approved_by',
        'dunning_level',
        'dunned_at',
        'objection_at',
        'objection_note',
        'quote_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_reverse_charge' => 'boolean',
        'party_snapshot' => 'array',
        'tax_breakdown' => 'array',
        'approved_at' => 'datetime',
        'dunned_at' => 'datetime',
        'objection_at' => 'datetime',
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

    /**
     * Summen inkl. Steueraufriss (MVP-162): Positionen MIT eigenem
     * tax_rate werden je Satz gruppiert (mehrere Steuersätze je Rechnung);
     * Positionen ohne Satz fallen auf den Kopfsatz zurück (Alt-Verhalten).
     * Rundung PRO SATZ (§ 14 UStG-üblich), Kopf trägt die Summe.
     */
    public function recalculate(): void {
        $byRate = [];
        foreach ($this->items as $item) {
            $rate = $item->tax_rate !== null ? (float) $item->tax_rate : (float) $this->tax_rate;
            $key = number_format($rate, 2, '.', '');
            $byRate[$key] = ($byRate[$key] ?? 0.0) + (float) $item->amount;
        }

        $sub = 0.0;
        $tax = 0.0;
        $breakdown = [];
        ksort($byRate);
        foreach ($byRate as $key => $net) {
            $net = round($net, 2);
            $rateTax = $this->is_reverse_charge ? 0.0 : round($net * ((float) $key) / 100, 2);
            $breakdown[] = ['rate' => (float) $key, 'net' => $net, 'tax' => $rateTax];
            $sub += $net;
            $tax += $rateTax;
        }

        $this->subtotal = (string) round($sub, 2);
        $this->tax_amount = (string) round($tax, 2);
        $this->total = (string) round($sub + $tax, 2);
        $this->tax_breakdown = $breakdown;
    }

    protected static function booted(): void {
        // Ausstellungs-Unveränderlichkeit (MVP-162): Anker ist der beim
        // OFFIZIELLEN Ausstellen eingefrorene Partei-Snapshot (issue()/
        // markSent() → freezeParties) — ab dann sind fachliche Felder
        // gesperrt, nur die MUTABLE_AFTER_ISSUE-Whitelist bleibt änderbar.
        static::updating(function (self $invoice): void {
            if ($invoice->getRawOriginal('party_snapshot') === null) {
                return; // noch nicht offiziell ausgestellt (z. B. Alt-/Testdaten)
            }
            $blocked = array_diff(array_keys($invoice->getDirty()), self::MUTABLE_AFTER_ISSUE);
            if ($blocked !== []) {
                throw new \RuntimeException(
                    'Ausgestellte Rechnungen sind unveränderlich (Felder: ' . implode(', ', $blocked) . ').',
                );
            }
        });
    }

    /**
     * Empfänger-/Verkäufer-Snapshot einfrieren (MVP-162) — beim Ausstellen;
     * spätere Stammdatenänderungen deuten den Beleg nie um.
     */
    public function freezeParties(): void {
        if ($this->party_snapshot !== null) {
            return; // bereits eingefroren
        }
        $this->party_snapshot = app(\App\Services\Invoicing\InvoicePartySnapshot::class)->capture($this);
    }

    /** Überfällig = ausgestellt/teilbezahlt und Fälligkeit überschritten. */
    public function isOverdue(): bool {
        return in_array($this->status, [self::STATUS_ISSUED, self::STATUS_PARTIALLY_PAID], true)
            && $this->due_on !== null
            && $this->due_on->isPast();
    }

    public function isCreditNote(): bool {
        return $this->type === self::TYPE_CREDIT_NOTE;
    }

    public function isMaterial(): bool {
        return $this->category === self::CATEGORY_MATERIAL;
    }

    /**
     * Beschriftung der Datumsangabe je nach Kategorie: Leistung ⇒ "Leistungsdatum",
     * Material ⇒ "Lieferdatum". {@see hasServicePeriod()} unterscheidet zwischen
     * Einzeldatum und Zeitraum.
     */
    public function dateLabelSingle(): string {
        return $this->isMaterial() ? (string) __('Lieferdatum') : (string) __('Leistungsdatum');
    }

    public function dateLabelPeriod(): string {
        return $this->isMaterial() ? (string) __('Lieferzeitraum') : (string) __('Leistungszeitraum');
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
            $this->due_on ??= now()->addDays($this->payment_terms_days ?? 14);
            $this->freezeParties();
        }
        $this->save();
    }

    /** Display-Label für PDF/Show (deutsch). */
    public function documentLabel(): string {
        return $this->isCreditNote() ? __('Gutschrift') : __('Rechnung');
    }

    /**
     * Sortierte, eindeutige Leistungsdaten der Positionen (§14 UStG).
     *
     * @return \Illuminate\Support\Collection<int, Carbon>
     */
    public function serviceDates(): \Illuminate\Support\Collection {
        return $this->items
            ->map(fn(InvoiceItem $i): ?Carbon => $i->service_date)
            ->filter()
            ->unique(fn(Carbon $d): string => $d->toDateString())
            ->sortBy(fn(Carbon $d): string => $d->toDateString())
            ->values();
    }

    public function serviceDateFrom(): ?Carbon {
        return $this->serviceDates()->first();
    }

    public function serviceDateTo(): ?Carbon {
        return $this->serviceDates()->last();
    }

    /**
     * True, wenn sich die Leistung über mehr als einen Tag erstreckt
     * (⇒ Leistungszeitraum im Kopf, Leistungsdatum je Position).
     */
    public function hasServicePeriod(): bool {
        return $this->serviceDates()->count() > 1;
    }

    /**
     * Das einzige Leistungsdatum, wenn die ganze Rechnung an einem Tag erbracht
     * wurde (⇒ Datum nur im Kopf nötig, nicht je Position). Sonst null.
     */
    public function serviceDateSingle(): ?Carbon {
        return $this->hasServicePeriod() ? null : $this->serviceDateFrom();
    }
}
