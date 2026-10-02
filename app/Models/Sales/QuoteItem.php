<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sales;

use App\Casts\{MoneyCast, PercentageCast};
use App\Models\Article\Article;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid, IsDocumentLine};
use App\Models\Contracts\DocumentLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Angebotsposition (Feature 066, MVP-170) — optional = Eventualposition,
 * accepted = Teilannahme-Entscheidung je Position.
 *
 * @property int $id
 * @property int $quote_id
 * @property int|null $article_id
 * @property bool $optional
 * @property bool|null $accepted
 * @property \CommonToolkit\ValueObjects\Percentage|null $tax_rate
 * @property \CommonToolkit\ValueObjects\Money|null $unit_price
 * @property \CommonToolkit\ValueObjects\Percentage|null $discount_percent
 * @property \CommonToolkit\ValueObjects\Percentage|null $labour_share_percent
 * @property \App\Enums\Billing\DocumentLineKind $line_kind
 * @property \CommonToolkit\ValueObjects\Money|null $unit_cost_amount
 * @property array<string, mixed>|null $calculation
 * @property \CommonToolkit\ValueObjects\Money|null $discount_amount
 */
class QuoteItem extends Model implements DocumentLine {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Sales\QuoteItemFactory> */
    use HasFactory;
    use HasSqid;
    use IsDocumentLine;

    protected $fillable = [
        'organization_id', 'quote_id', 'article_id', 'position', 'description',
        'quantity', 'unit', 'unit_price', 'discount_percent', 'discount_amount',
        'tax_rate', 'tax_category', 'optional', 'accepted', 'labour_share_percent',
        'line_kind',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['line_kind' => 'item'];

    /** MVP-1055: Kalkulation einer Leistung als Schnappschuss — spätere Änderungen am Artikel ändern das Angebot nicht. */
    protected static function booted(): void {
        static::creating(static function (self $item): void {
            if ($item->calculation !== null || $item->article_id === null || ! $item->lineKind()->isPriced()) {
                return;
            }
            $article = \App\Models\Article\Article::query()->find($item->article_id);
            $calculation = $article !== null ? app(\App\Services\Article\ServiceCalculationService::class)->calculate($article) : null;
            if ($calculation === null) {
                return;
            }
            $item->calculation = $calculation->toSnapshot();
            $item->unit_cost_amount ??= $calculation->cost;
        });
    }

    /** @var array<string, string> */
    protected $casts = [
        'optional' => 'boolean',
        'accepted' => 'boolean',
        // Angebote rechnen in Euro (s. Quote::recalculate()).
        'unit_price' => MoneyCast::class,
        'discount_percent' => PercentageCast::class . ':2',
        'labour_share_percent' => PercentageCast::class . ':2',
        'line_kind' => \App\Enums\Billing\DocumentLineKind::class,
        'unit_cost_amount' => MoneyCast::class . ':currency,4',
        'calculation' => 'array',
        'discount_amount' => MoneyCast::class,
        'tax_rate' => PercentageCast::class . ':2',
    ];

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo {
        return $this->belongsTo(Quote::class);
    }

    /** @return BelongsTo<Quote, $this> */
    public function lineDocument(): BelongsTo {
        return $this->quote();
    }

    /**
     * Zählt zur Angebotssumme (MVP-1054): vor der Entscheidung nur feste
     * Positionen, danach nur Angenommenes; Titel und Text nie, eine
     * Alternative erst, wenn sie gewählt ist.
     */
    public function countsInTotal(): bool {
        $kind = $this->lineKind();
        if (! $kind->isPriced()) {
            return false;
        }

        return $this->accepted ?? ($kind === \App\Enums\Billing\DocumentLineKind::Item && ! $this->optional);
    }

    /**
     * Optionaler Artikelbezug (Feature 140); wandert über Annahme-Snapshot
     * und Überführung mit in die Rechnungsposition.
     *
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }
}
