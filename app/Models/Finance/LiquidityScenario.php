<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LiquidityScenario.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Szenario zur Liquiditätsvorschau (MVP-954): Zahlungsverzug der Kunden,
 * Veränderung der Ein- und Auszahlungen, geplante Investitionen und
 * Einzelposten. Ändert nichts an den Büchern.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int $receipt_delay_days
 * @property numeric-string $inflow_change_percent
 * @property numeric-string $outflow_change_percent
 * @property bool $is_including_investments
 * @property string|null $note
 * @property int|null $created_by
 */
class LiquidityScenario extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'name', 'receipt_delay_days', 'inflow_change_percent', 'outflow_change_percent', 'is_including_investments', 'note', 'created_by'];

    /** @var array<string, string> */
    protected $casts = [
        'receipt_delay_days' => 'integer',
        'inflow_change_percent' => 'decimal:2',
        'outflow_change_percent' => 'decimal:2',
        'is_including_investments' => 'boolean',
    ];

    /** @return HasMany<LiquidityScenarioItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(LiquidityScenarioItem::class)->orderBy('expected_on');
    }
}
