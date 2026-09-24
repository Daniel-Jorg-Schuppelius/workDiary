<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostEstimate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Costing;

use App\Models\Catalog\CatalogRegistry;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Contracts\HasDocumentLines;
use App\Models\Gaeb\BillOfQuantity;
use App\Models\Project\Project;
use App\Services\Billing\DocumentTotalsCalculator;
use App\Services\Billing\Dto\DocumentTotalsContext;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Eine Kostenermittlung nach DIN 276 / HOAI (Feature 109, MVP-646).
 *
 * Die vier Stufen **lösen einander nicht ab**: Der Vergleich zwischen
 * Kostenschätzung, -berechnung, -anschlag und -feststellung *ist* die
 * Kostenkontrolle. Eine neue Ermittlung tritt deshalb neben die alte, nie an
 * ihre Stelle.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $project_id
 * @property int|null $bill_of_quantity_id
 * @property string $name
 * @property string $stage
 * @property string|null $method
 * @property \Illuminate\Support\Carbon $determined_on
 * @property string $currency
 * @property string $source
 * @property int|null $catalog_registry_id
 * @property string|null $note
 */
class CostEstimate extends Model implements HasDocumentLines {
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Costing\CostEstimateFactory> */
    use HasFactory;
    use HasSqid;

    /** Die vier HOAI-Stufen, in der Reihenfolge des Vorhabens. */
    public const STAGE_ESTIMATE = 'estimate';
    public const STAGE_CALCULATION = 'calculation';
    public const STAGE_QUOTE = 'quote';
    public const STAGE_FINAL = 'final';

    public const STAGES = [self::STAGE_ESTIMATE, self::STAGE_CALCULATION, self::STAGE_QUOTE, self::STAGE_FINAL];

    /** Woher die Zahlen stammen — eine fremde Datei ist etwas anderes als eigene Ableitung. */
    public const SOURCE_IMPORT = 'x51_import';
    public const SOURCE_DERIVED = 'derived';
    public const SOURCE_MANUAL = 'manual';

    protected $table = 'cost_estimates';

    protected $fillable = [
        'organization_id', 'project_id', 'bill_of_quantity_id', 'name', 'stage',
        'method', 'determined_on', 'currency', 'source', 'catalog_registry_id',
        'note', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'determined_on' => 'date',
    ];

    /** @return HasMany<CostEstimateItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(CostEstimateItem::class, 'cost_estimate_id')->orderBy('position');
    }

    /** @return HasMany<CostEstimateItem, $this> */
    public function lines(): HasMany {
        return $this->items();
    }

    public function documentCurrency(): CurrencyCode {
        return CurrencyCode::tryFrom(strtoupper((string) $this->currency)) ?? CurrencyCode::Euro;
    }

    /** Kostenermittlungen führen keine Steuer — die Summe ist das Netto. */
    public function documentTotals(): array {
        return app(DocumentTotalsCalculator::class)->totals($this->items, new DocumentTotalsContext(currency: $this->documentCurrency()));
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<BillOfQuantity, $this> */
    public function billOfQuantity(): BelongsTo {
        return $this->belongsTo(BillOfQuantity::class);
    }

    /** @return BelongsTo<CatalogRegistry, $this> */
    public function registry(): BelongsTo {
        return $this->belongsTo(CatalogRegistry::class, 'catalog_registry_id');
    }

    public function stageLabel(): string {
        return (string) __('costing.stage.' . $this->stage);
    }
}
