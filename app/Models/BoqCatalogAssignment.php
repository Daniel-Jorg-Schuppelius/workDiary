<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqCatalogAssignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Eine Katalogzuordnung (Feature 109, MVP-586). Derselbe Datensatz trägt die
 * Kostengruppe nach DIN 276, den Leistungsbereich, das Gebäude, den
 * Kostenträger oder die Modellkennung — GAEB kennt dafür nur einen Mechanismus.
 *
 * Sie hängt an einer Position, einem Abschnitt **oder einer Teilmenge**: eine
 * Position kann sich auf mehrere Kostengruppen verteilen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $bill_of_quantity_id
 * @property string $assignable_type
 * @property int $assignable_id
 * @property string $catalog_key
 * @property string $code
 * @property string|null $quantity
 * @property string $source
 */
class BoqCatalogAssignment extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'bill_of_quantity_id',
        'assignable_type',
        'assignable_id',
        'catalog_key',
        'code',
        'quantity',
        'source',
    ];

    protected $casts = [
        // Anteil einer Zuordnung, wenn der Katalog ihn zulässt.
        'quantity' => 'decimal:4',
    ];

    /** @return MorphTo<Model, $this> */
    public function assignable(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<BillOfQuantity, $this> */
    public function billOfQuantity(): BelongsTo {
        return $this->belongsTo(BillOfQuantity::class);
    }
}
