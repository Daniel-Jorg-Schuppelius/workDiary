<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalCaseAsset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Rental;

use App\Models\Asset;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Leihobjekt einer Verleihakte. Tauschgeräte (MVP-264) verketten sich über
 * replaced_by_id — die Historie bleibt vollständig in der Akte.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $rental_case_id
 * @property int $asset_id
 * @property string $status
 * @property int|null $replaced_by_id
 * @property array<int, string>|null $accessories
 */
class RentalCaseAsset extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['planned', 'handed_over', 'returned', 'swapped'];

    protected $fillable = [
        'organization_id', 'rental_case_id', 'asset_id', 'status',
        'replaced_by_id', 'accessories', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'accessories' => 'array',
    ];

    /** @return BelongsTo<RentalCase, $this> */
    public function rentalCase(): BelongsTo {
        return $this->belongsTo(RentalCase::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<self, $this> */
    public function replacedBy(): BelongsTo {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }
}
