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

use App\Casts\{MoneyCast, PercentageCast};
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
 * @property int|null $article_id
 * @property \Illuminate\Support\Carbon|null $service_date
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property \CommonToolkit\ValueObjects\Money|null $unit_price
 * @property \CommonToolkit\ValueObjects\Percentage|null $discount_percent
 * @property \CommonToolkit\ValueObjects\Money|null $discount_amount
 * @property \CommonToolkit\ValueObjects\Money|null $amount
 * @property \CommonToolkit\ValueObjects\Percentage|null $tax_rate
 * @property int $position
 */
class InvoiceItem extends Model {
    /**
     * Positionen einer ausgestellten Rechnung sind unveränderlich
     * (Sicherheitsscan 2026-08-23, S-59).
     *
     * Die Rechnung selbst trägt den Freeze seit MVP-162 — ihre Positionen
     * nicht. Der Schutz lag allein in `InvoicePolicy::update`, also in den
     * Controllern: ein Bulk-Update oder ein Schreibpfad, der die Policy nicht
     * fragt, konnte den Betrag einer bereits gestellten Rechnung ändern, ohne
     * dass die Rechnung selbst angefasst wurde. Der Anker ist derselbe wie
     * dort: der eingefrorene Partei-Snapshot.
     */
    protected static function booted(): void {
        $assertMutable = static function (self $item): void {
            $invoice = $item->invoice;

            if (! $invoice instanceof Invoice || $invoice->getRawOriginal('party_snapshot') === null) {
                return; // Entwurf oder Alt-/Testdaten
            }

            throw new \RuntimeException(
                'Positionen ausgestellter Rechnungen sind unveränderlich (Rechnung ' . (string) $invoice->number . ').',
            );
        };

        static::updating($assertMutable);
        static::deleting($assertMutable);
    }

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
        'article_id',
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
        // Währung kommt vom Beleg — Positionen haben keine eigene Spalte.
        // Einzelpreis mit 4 NK (Migration widen_invoice_item_precision).
        'unit_price' => MoneyCast::class . ':invoice.currency,4',
        'discount_percent' => PercentageCast::class . ':2',
        'discount_amount' => MoneyCast::class . ':invoice.currency',
        'amount' => MoneyCast::class . ':invoice.currency',
        'tax_rate' => PercentageCast::class . ':2',
    ];

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
     * Optionaler Artikelbezug (Feature 140) — Auswertungsanker, kein
     * Preisautomat: Beschreibung/Einheit/Preis bleiben Positionswerte.
     *
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
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
