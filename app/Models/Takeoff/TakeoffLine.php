<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoffLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Takeoff;

use App\Enums\Takeoff\TakeoffFormula;
use App\Models\Article\Article;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Gaeb\BoqItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zeile eines Aufmaßblatts (MVP-1058): Formel, Werte als Dezimaltexte (bei der
 * freien Formel der Ausdruck), Faktor (negativ = Abzug), gerechnete Menge und
 * Ziel der Menge (LV-Position oder Artikel).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $takeoff_id
 * @property int $position
 * @property string|null $label
 * @property string|null $description
 * @property TakeoffFormula $formula
 * @property list<string> $values
 * @property string $factor
 * @property string|null $quantity
 * @property string|null $unit
 * @property int|null $boq_item_id
 * @property int|null $article_id
 */
class TakeoffLine extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'takeoff_id',
        'position',
        'label',
        'description',
        'formula',
        'values',
        'factor',
        'quantity',
        'unit',
        'boq_item_id',
        'article_id',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['factor' => '1'];

    /** @var array<string, string> */
    protected $casts = [
        'formula' => TakeoffFormula::class,
        'values' => 'array',
        'factor' => 'decimal:3',
        'quantity' => 'decimal:4',
        'position' => 'integer',
    ];

    /** @return BelongsTo<Takeoff, $this> */
    public function takeoff(): BelongsTo {
        return $this->belongsTo(Takeoff::class);
    }

    /** @return BelongsTo<BoqItem, $this> */
    public function boqItem(): BelongsTo {
        return $this->belongsTo(BoqItem::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }
}
