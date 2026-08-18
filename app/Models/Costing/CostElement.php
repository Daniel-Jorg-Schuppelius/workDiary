<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostElement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Costing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Kostenelement eines Baukostenkatalogs (Feature 109, MVP-645).
 *
 * **Der Kennwert ist eine Spanne, keine Zahl.** Von, Mittel und bis stehen
 * nebeneinander; nur den Mittelwert zu führen verschwiege, wie sicher er ist.
 * Wer damit rechnet, soll sehen, worauf er sich stützt.
 *
 * @property int $id
 * @property int $cost_element_catalog_id
 * @property string|null $code
 * @property string $label
 * @property string|null $unit
 * @property int|null $article_id
 * @property string|null $unit_price_from
 * @property string|null $unit_price_avg
 * @property string|null $unit_price_to
 * @property string|null $remark
 * @property int $level
 * @property string|null $parent_code
 * @property int $position
 */
class CostElement extends Model {
    protected $table = 'cost_elements';

    protected $fillable = [
        'cost_element_catalog_id', 'code', 'label', 'unit', 'article_id',
        'unit_price_from', 'unit_price_avg', 'unit_price_to',
        'remark', 'level', 'parent_code', 'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'unit_price_from' => 'decimal:4',
        'unit_price_avg' => 'decimal:4',
        'unit_price_to' => 'decimal:4',
        'level' => 'integer',
        'position' => 'integer',
    ];

    /** @return BelongsTo<CostElementCatalog, $this> */
    public function catalog(): BelongsTo {
        return $this->belongsTo(CostElementCatalog::class, 'cost_element_catalog_id');
    }

    /**
     * Der eigene Artikel, dem dieser Kennwert entspricht (MVP-645).
     *
     * Die Verknüpfung **ersetzt keinen Preis**: Der Kennwert bleibt ein
     * Anhaltspunkt aus fremder Quelle, der eigene Preis bleibt der eigene.
     *
     * @return BelongsTo<\App\Models\Article, $this>
     */
    public function article(): BelongsTo {
        return $this->belongsTo(\App\Models\Article::class, 'article_id');
    }

    /**
     * Der Kennwert, mit dem zu rechnen ist: der Mittelwert, ersatzweise die
     * Mitte der Spanne.
     *
     * Ohne jede Angabe bleibt es `null` — eine erfundene Null wäre schlimmer
     * als ein Element ohne Kennwert.
     */
    public function benchmark(): ?float {
        if ($this->unit_price_avg !== null) {
            return (float) $this->unit_price_avg;
        }
        if ($this->unit_price_from !== null && $this->unit_price_to !== null) {
            return round(((float) $this->unit_price_from + (float) $this->unit_price_to) / 2, 4);
        }

        return $this->unit_price_from === null ? null : (float) $this->unit_price_from;
    }
}
