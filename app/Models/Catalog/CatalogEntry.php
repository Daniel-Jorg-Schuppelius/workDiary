<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\App;

/**
 * Ein Eintrag eines Katalogstamms (Feature 109, MVP-637): Nummer,
 * Kurzbezeichnung, Ebene.
 *
 * **Nur Nummer und Kurzbezeichnung** (D6) — der Normtext der DIN 276 und die
 * STLB-Bau-Texte sind lizenzpflichtig und werden nicht ausgeliefert.
 *
 * @property int $id
 * @property int $catalog_registry_id
 * @property string $code
 * @property string $label
 * @property array<string, string>|null $labels
 * @property int $level
 * @property string|null $parent_code
 * @property int $position
 */
class CatalogEntry extends Model {
    protected $table = 'catalog_entries';

    protected $fillable = [
        'catalog_registry_id', 'code', 'label', 'labels', 'level', 'parent_code', 'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'labels' => 'array',
        'level' => 'integer',
        'position' => 'integer',
    ];

    /** @return BelongsTo<CatalogRegistry, $this> */
    public function registry(): BelongsTo {
        return $this->belongsTo(CatalogRegistry::class, 'catalog_registry_id');
    }

    /**
     * Kurzbezeichnung in der aktiven Sprache; die deutsche ist der Rückfall,
     * weil sie die amtliche ist.
     */
    public function localizedLabel(?string $locale = null): string {
        $locale ??= App::getLocale();
        if ($locale === 'de') {
            return $this->label;
        }

        $labels = $this->labels ?? [];

        return $labels[$locale] ?? $this->label;
    }

    /** „310 Baugrube" — wie es in Listen und Berichten steht. */
    public function display(?string $locale = null): string {
        return $this->code . ' ' . $this->localizedLabel($locale);
    }
}
