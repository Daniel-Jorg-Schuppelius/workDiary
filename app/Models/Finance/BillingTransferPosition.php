<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferPosition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Rechnungssicht einer Übergabe (MVP-487): beim Bestätigen aus Taktung,
 * Preisfindung und Standardleistung erzeugt und danach prüfbar. Die Ziele
 * senden genau diese Zeilen; die Quell-Zuordnung bleibt im
 * {@see BillingTransferItem}.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $billing_transfer_id
 * @property int $position
 * @property string $source_kind
 * @property int|null $project_id
 * @property string|null $kind
 * @property array<int, int>|null $source_ids
 * @property int|null $primary_source_id
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $ai_assisted_at
 * @property string $quantity
 * @property string|null $unit_name
 * @property string $unit_price
 * @property string|null $vat_rate
 * @property string $amount
 * @property string|null $article_id
 * @property string|null $service_source
 * @property string|null $price_source
 * @property Carbon|null $service_from
 * @property Carbon|null $service_to
 */
class BillingTransferPosition extends Model {
    use BelongsToOrganization;

    public const KIND_TIME = 'time';

    public const KIND_MATERIAL = 'material';

    protected $fillable = [
        'organization_id',
        'billing_transfer_id',
        'position',
        'source_kind',
        'project_id',
        'kind',
        'source_ids',
        'primary_source_id',
        'name',
        'description',
        'ai_assisted_at',
        'quantity',
        'unit_name',
        'unit_price',
        'vat_rate',
        'amount',
        'article_id',
        'service_source',
        'price_source',
        'service_from',
        'service_to',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'integer',
        'source_ids' => 'array',
        'primary_source_id' => 'integer',
        'ai_assisted_at' => 'datetime',
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'vat_rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'service_from' => 'date',
        'service_to' => 'date',
    ];

    /** @return BelongsTo<BillingTransfer, $this> */
    public function transfer(): BelongsTo {
        return $this->belongsTo(BillingTransfer::class, 'billing_transfer_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    public function quantityFloat(): float {
        return (float) $this->quantity;
    }

    public function unitPriceFloat(): float {
        return (float) $this->unit_price;
    }

    public function amountFloat(): float {
        return (float) $this->amount;
    }

    /** Position ohne Preis — die Übergabe meldet sie vor dem Senden. */
    public function isUnpriced(): bool {
        return $this->unitPriceFloat() <= 0.0;
    }

    /** Woher der Einzelpreis stammt (Anzeige in der Vorschau). */
    public function priceSourceLabel(): string {
        return (new \App\Services\Invoicing\BlockPrice(0.0, (string) $this->price_source))->sourceLabel();
    }
}
