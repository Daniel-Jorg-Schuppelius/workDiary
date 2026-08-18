<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogCodeMapping.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entsprechung eines Schlüssels zwischen zwei Katalogausgaben (Feature 109,
 * MVP-641) — etwa DIN 276-1:2008-12 → DIN 276:2018-12.
 *
 * **Die Tabelle ist ein Vorschlagswerk, keine Umrechnung.** Ein Wechsel der
 * Ausgabe ist eine fachliche Entscheidung; wo die neue Ausgabe neu gegliedert
 * hat, gibt es keine Entsprechung, und die Lücke ist die ehrlichere Auskunft
 * als eine erfundene Zeile.
 *
 * @property int $id
 * @property int $from_registry_id
 * @property int $to_registry_id
 * @property string $from_code
 * @property string $to_code
 * @property string|null $note
 */
class CatalogCodeMapping extends Model {
    protected $table = 'catalog_code_mappings';

    protected $fillable = ['from_registry_id', 'to_registry_id', 'from_code', 'to_code', 'note'];

    /** @return BelongsTo<CatalogRegistry, $this> */
    public function fromRegistry(): BelongsTo {
        return $this->belongsTo(CatalogRegistry::class, 'from_registry_id');
    }

    /** @return BelongsTo<CatalogRegistry, $this> */
    public function toRegistry(): BelongsTo {
        return $this->belongsTo(CatalogRegistry::class, 'to_registry_id');
    }
}
