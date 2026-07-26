<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $invoice_id
 * @property int|null $time_entry_id
 * @property int|null $expense_id
 * @property int|null $material_usage_id
 * @property int|null $tour_id
 * @property \Illuminate\Support\Carbon|null $service_date
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property string $unit_price
 * @property string $amount
 * @property int $position
 */
class InvoiceItem extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'tax_rate',
        'tax_category',
        'organization_id',
        'invoice_id',
        'time_entry_id',
        'expense_id',
        'material_usage_id',
        'tour_id',
        'rental_charge_id',
        'settled_invoice_id',
        'service_date',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'amount',
        'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'service_date' => 'date',
        // Mengen-/Preispräzision der Quellposten erhalten (Material 3 NK, km-Satz 4 NK); Zeilenbetrag 2 NK.
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void {
        static::saving(function (InvoiceItem $i): void {
            // MVP-416: Zeilennetto inkl. Positionsrabatt (Prozent XOR Betrag).
            $i->amount = \App\Services\Invoicing\InvoiceTotalsCalculator::lineNet(
                (float) $i->quantity,
                (string) $i->unit_price,
                $i->discount_percent !== null ? (float) $i->discount_percent : null,
                $i->discount_amount !== null ? (string) $i->discount_amount : null,
                $i->invoice->currency ?? \CommonToolkit\Enums\CurrencyCode::Euro,
            )->getAmount();
        });

        // Beim Löschen alle Quellposten wieder freigeben (Spese→Approved, Zeiten→exported=false,
        // Material→billed=false, Tour→travel_billed=false). Im deleting-Hook: danach ist die Pivot-Zuordnung weg.
        static::deleting(function (InvoiceItem $i): void {
            if ($i->expense_id !== null) {
                $expense = Expense::query()->find($i->expense_id);
                if ($expense !== null && $expense->status === \App\Enums\Expense\ExpenseStatus::Invoiced) {
                    $expense->status = \App\Enums\Expense\ExpenseStatus::Approved;
                    $expense->saveQuietly();
                }
            }

            $entryIds = $i->timeEntries()->pluck('time_entries.id')->all();
            if ($i->time_entry_id !== null) {
                $entryIds[] = $i->time_entry_id;
            }
            if ($entryIds !== []) {
                TimeEntry::query()->whereKey(array_unique($entryIds))->update(['exported' => false]);
            }
            if ($i->material_usage_id !== null) {
                MaterialUsage::query()->whereKey($i->material_usage_id)->update(['billed' => false]);
            }
            if ($i->tour_id !== null) {
                Tour::query()->whereKey($i->tour_id)->update(['travel_billed' => false]);
            }
            if ($i->rental_charge_id !== null) {
                $charge = \App\Models\Rental\RentalCharge::query()->find($i->rental_charge_id);
                if ($charge !== null && $charge->status === \App\Enums\Rental\RentalChargeStatus::Invoiced) {
                    $charge->forceFill([
                        'status' => \App\Enums\Rental\RentalChargeStatus::Released->value,
                        'invoice_id' => null,
                        'invoiced_at' => null,
                    ])->saveQuietly();
                }
            }
        });
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<TimeEntry, $this> */
    public function timeEntry(): BelongsTo {
        return $this->belongsTo(TimeEntry::class);
    }

    /**
     * Alle Zeiteinträge, die diese (ggf. durch Taktung/Zusammenfassung
     * gebündelte) Position abbildet. Die Einzel-FK {@see timeEntry()} verweist
     * weiterhin auf den ersten Eintrag des Blocks.
     *
     * @return BelongsToMany<TimeEntry, $this>
     */
    public function timeEntries(): BelongsToMany {
        return $this->belongsToMany(TimeEntry::class, 'invoice_item_time_entries')
            ->withTimestamps();
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo {
        return $this->belongsTo(Expense::class);
    }

    /** @return BelongsTo<MaterialUsage, $this> */
    public function materialUsage(): BelongsTo {
        return $this->belongsTo(MaterialUsage::class);
    }

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo {
        return $this->belongsTo(Tour::class);
    }

    /**
     * Angerechnete Abschlagsrechnung (§ 14 Abs. 5 UStG): gesetzt auf der
     * Absetzungsposition einer Schlussrechnung, verweist auf die
     * Abschlags-/Anzahlungsrechnung, deren Teilentgelt hier abgesetzt wird.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function settledInvoice(): BelongsTo {
        return $this->belongsTo(Invoice::class, 'settled_invoice_id');
    }
}
