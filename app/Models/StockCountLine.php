<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockCountLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Inventory\{OwnershipType, StockState};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zählzeile einer Inventur (Feature 048, MVP-069). Mandantengrenze transitiv
 * über {@see StockCount}.
 *
 * @property int $id
 * @property int $stock_count_id
 * @property StockState $stock_state
 * @property OwnershipType $ownership_type
 * @property numeric-string $book_qty
 * @property numeric-string|null $counted_qty
 * @property bool $applied
 * @property int|null $counted_by
 */
class StockCountLine extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'stock_count_id',
        'article_variant_id',
        'stock_state',
        'ownership_type',
        'book_qty',
        'counted_qty',
        'applied',
        'counted_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'stock_state' => StockState::class,
        'ownership_type' => OwnershipType::class,
        'book_qty' => 'decimal:4',
        'counted_qty' => 'decimal:4',
        'applied' => 'boolean',
    ];

    /**
     * Differenz counted − book; null solange nicht gezählt.
     *
     * @return numeric-string|null
     */
    public function difference(): ?string {
        if ($this->counted_qty === null) {
            return null;
        }

        return bcsub($this->counted_qty, $this->book_qty, 4);
    }

    /** @return BelongsTo<StockCount, $this> */
    public function count(): BelongsTo {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }
}
