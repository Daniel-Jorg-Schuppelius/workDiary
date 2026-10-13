<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderConfirmationItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sales;

use App\Casts\{MoneyCast, PercentageCast};
use App\Enums\Billing\DocumentLineKind;
use App\Models\Article\Article;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid, IsDocumentLine};
use App\Models\Contracts\DocumentLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Position einer Auftragsbestätigung (Phase 145, MVP-1162). */
class OrderConfirmationItem extends Model implements DocumentLine {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Sales\OrderConfirmationItemFactory> */
    use HasFactory;
    use HasSqid;
    use IsDocumentLine;

    protected $fillable = [
        'organization_id', 'order_confirmation_id', 'article_id', 'position', 'line_kind',
        'description', 'quantity', 'unit', 'unit_price', 'discount_percent', 'discount_amount',
        'tax_rate', 'tax_category',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['line_kind' => 'item'];

    /** @var array<string, string> */
    protected $casts = [
        'line_kind' => DocumentLineKind::class,
        'unit_price' => MoneyCast::class,
        'discount_percent' => PercentageCast::class . ':2',
        'discount_amount' => MoneyCast::class,
        'tax_rate' => PercentageCast::class . ':2',
    ];

    /** @return BelongsTo<OrderConfirmation, $this> */
    public function orderConfirmation(): BelongsTo {
        return $this->belongsTo(OrderConfirmation::class);
    }

    /** @return BelongsTo<OrderConfirmation, $this> */
    public function lineDocument(): BelongsTo {
        return $this->orderConfirmation();
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }
}
