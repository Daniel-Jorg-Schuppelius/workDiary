<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostElementCatalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Costing;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Baukostenkatalog nach GAEB X50 (Feature 109, MVP-645) — ein
 * Nachschlagewerk, kein Vorhaben.
 *
 * Er liefert Kennwerte für die frühen HOAI-Stufen, für die WorkDiary sonst
 * keine Zahlen hat: „Außenwand, zweischalig, 36,5 cm — 320 €/m²".
 *
 * `full_element_numbers` merkt sich die Bauform der Quelle: X50.2 nummeriert
 * vollständig (`EleNo`), X50.1 in Teilen (`ElePart`). Der Export muss dieselbe
 * wählen, sonst liest die Gegenseite andere Nummern.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $edition
 * @property \Illuminate\Support\Carbon|null $valid_on
 * @property string $currency
 * @property bool $full_element_numbers
 * @property string $source
 * @property string|null $note
 * @property bool $active
 */
class CostElementCatalog extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const SOURCE_IMPORT = 'x50_import';
    public const SOURCE_MANUAL = 'manual';

    protected $table = 'cost_element_catalogs';

    protected $fillable = [
        'organization_id', 'name', 'edition', 'valid_on', 'currency',
        'full_element_numbers', 'source', 'note', 'active', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'valid_on' => 'date',
        'full_element_numbers' => 'boolean',
        'active' => 'boolean',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'currency' => 'EUR',
    ];

    /** @return HasMany<CostElement, $this> */
    public function elements(): HasMany {
        return $this->hasMany(CostElement::class, 'cost_element_catalog_id')->orderBy('position');
    }
}
