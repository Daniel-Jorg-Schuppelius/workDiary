<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MetalQuotation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;

/**
 * Metallnotierung (Feature 107, MVP-564): Tagespreis eines Rohstoffs in €/kg
 * (Kupfer = DEL-Notiz). Der jüngste Eintrag je Metall ist die aktuelle
 * Notierung für die Kupferzuschlag-Berechnung der DATANORM-Katalogartikel.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $metal
 * @property \CommonToolkit\ValueObjects\Money|null $price_per_kg
 * @property \Illuminate\Support\Carbon $quoted_at
 */
class MetalQuotation extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'metal',
        'price_per_kg',
        'currency',
        'quoted_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'price_per_kg' => MoneyCast::class . ':currency,4',
        'quoted_at' => 'date',
    ];
}
