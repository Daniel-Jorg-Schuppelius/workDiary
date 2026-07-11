<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalAccessoryItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Rental;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Zubehörposition eines Übergabe- oder Rücknahmeprotokolls — fehlendes
 * Zubehör bei Rücknahme wird über present=false nachweisbar.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $report_type
 * @property int $report_id
 * @property string $label
 * @property int $quantity
 * @property bool $present
 */
class RentalAccessoryItem extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'report_type', 'report_id', 'label', 'quantity',
        'present', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'quantity' => 'integer',
        'present' => 'boolean',
    ];

    /** @return MorphTo<Model, $this> */
    public function report(): MorphTo {
        return $this->morphTo();
    }
}
