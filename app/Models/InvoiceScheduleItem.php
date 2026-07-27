<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceScheduleItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\{MoneyCast, PercentageCast};
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Positionsvorlage eines Abrechnungsplans (MVP-415); Platzhalter
 * {zeitraum_von}/{zeitraum_bis} werden je Lauf ersetzt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $invoice_schedule_id
 * @property int $position
 * @property string $description
 * @property string $quantity
 * @property string|null $unit
 * @property \CommonToolkit\ValueObjects\Money|null $unit_price
 * @property \CommonToolkit\ValueObjects\Percentage|null $discount_percent
 * @property \CommonToolkit\ValueObjects\Money|null $discount_amount
 * @property \CommonToolkit\ValueObjects\Percentage|null $tax_rate
 * @property string|null $tax_category
 */
class InvoiceScheduleItem extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'invoice_schedule_id',
        'position',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'tax_rate',
        'tax_category',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'integer',
        'quantity' => 'decimal:3',
        // Rechnungspläne führen keine Währungsspalte — Euro wie beim Angebot.
        'unit_price' => MoneyCast::class . ':currency,4',
        'discount_percent' => PercentageCast::class . ':2',
        'discount_amount' => MoneyCast::class . ':currency',
        'tax_rate' => PercentageCast::class . ':2',
    ];

    /** @return BelongsTo<InvoiceSchedule, $this> */
    public function schedule(): BelongsTo {
        return $this->belongsTo(InvoiceSchedule::class, 'invoice_schedule_id');
    }
}
