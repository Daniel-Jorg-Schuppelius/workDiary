<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalWageItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Externe vergütungsrelevante Position (Feature-103-Delta): Essensgeld,
 * Kilometer, Erschwernis-/Akkordzulagen etc. je Mitarbeiter/Tag mit
 * Lohnartenbezug — wird vom Zeitwirtschafts-Export als zusätzliche Zeile
 * übernommen. Erfassung per Import (`wage-items:import`) oder API.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property Carbon $item_date
 * @property string $wage_type_code
 * @property string $quantity
 * @property string $unit
 * @property string|null $note
 * @property string|null $source
 */
class ExternalWageItem extends Model {
    use Auditable;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'user_id',
        'item_date',
        'wage_type_code',
        'quantity',
        'unit',
        'note',
        'source',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'item_date' => 'date',
        'quantity' => 'decimal:2',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
