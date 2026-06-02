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
        'organization_id',
        'invoice_id',
        'time_entry_id',
        'expense_id',
        'material_usage_id',
        'tour_id',
        'service_date',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'amount',
        'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'service_date' => 'date',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void {
        static::saving(function (InvoiceItem $i): void {
            $i->amount = (string) round(((float) $i->quantity) * ((float) $i->unit_price), 2);
        });

        // Wird eine InvoiceItem mit verknuepfter Expense geloescht, geben wir die
        // Spese wieder frei (Status zurueck auf Approved), damit sie erneut
        // einer anderen Rechnung zugeordnet werden kann.
        static::deleted(function (InvoiceItem $i): void {
            if ($i->expense_id === null) {
                return;
            }
            $expense = Expense::query()->find($i->expense_id);
            if ($expense !== null && $expense->status === \App\Enums\Expense\ExpenseStatus::Invoiced) {
                $expense->status = \App\Enums\Expense\ExpenseStatus::Approved;
                $expense->saveQuietly();
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
}
