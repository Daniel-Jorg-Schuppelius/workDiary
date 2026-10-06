<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqCallOff.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Gaeb;

use App\Enums\Gaeb\BoqCallOffStatus;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Invoicing\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Abruf aus einem Rahmen-LV (MVP-931): Positionen mit Mengen aus dem Rahmen,
 * abgerechnet über einen Rechnungsentwurf.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $bill_of_quantity_id
 * @property int $number
 * @property string $title
 * @property Carbon|null $ordered_on
 * @property Carbon|null $due_on
 * @property BoqCallOffStatus $status
 * @property int|null $invoice_id
 * @property string|null $note
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class BoqCallOff extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'bill_of_quantity_id', 'number', 'title', 'ordered_on', 'due_on', 'status', 'invoice_id', 'note', 'created_by', 'updated_by'];

    /** @var array<string, string> */
    protected $casts = ['number' => 'integer', 'ordered_on' => 'date', 'due_on' => 'date', 'status' => BoqCallOffStatus::class];

    /** @return BelongsTo<BillOfQuantity, $this> */
    public function billOfQuantity(): BelongsTo {
        return $this->belongsTo(BillOfQuantity::class);
    }

    /** @return HasMany<BoqCallOffItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(BoqCallOffItem::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }

    /** Eine stornierte Rechnung gibt den Abruf wieder zur Abrechnung frei. */
    public function activeInvoice(): ?Invoice {
        $invoice = $this->invoice;

        return $invoice instanceof Invoice && $invoice->status !== InvoiceStatus::Cancelled ? $invoice : null;
    }
}
