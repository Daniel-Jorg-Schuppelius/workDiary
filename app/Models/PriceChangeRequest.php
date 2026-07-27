<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceChangeRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\{MoneyCast, PercentageCast};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Antrag auf Übernahme eines Verkaufspreisvorschlags (Feature 050, MVP-095,
 * Vier-Augen-Modus): friert Einkaufspreis, Vorschlag und Marge zum
 * Antragszeitpunkt ein; genehmigt wird nur, wenn der zur Entscheidung neu
 * berechnete Vorschlag noch übereinstimmt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $supplier_catalog_item_id
 * @property int $article_id
 * @property int|null $pricing_margin_rule_id
 * @property \CommonToolkit\ValueObjects\Money|null $purchase_price_snapshot
 * @property \CommonToolkit\ValueObjects\Money|null $suggested_price
 * @property \CommonToolkit\ValueObjects\Percentage|null $margin_snapshot
 * @property string $status
 * @property int $requested_by
 * @property int|null $decided_by
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property string|null $decision_note
 */
class PriceChangeRequest extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'organization_id',
        'supplier_catalog_item_id',
        'article_id',
        'pricing_margin_rule_id',
        'purchase_price_snapshot',
        'suggested_price',
        'margin_snapshot',
        'status',
        'requested_by',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'purchase_price_snapshot' => MoneyCast::class . ':currency,4',
        'suggested_price' => MoneyCast::class . ':currency,4',
        'margin_snapshot' => PercentageCast::class . ':3',
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<SupplierCatalogItem, $this> */
    public function item(): BelongsTo {
        return $this->belongsTo(SupplierCatalogItem::class, 'supplier_catalog_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
