<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalConditionItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Rental;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Checklisten-/Zustandsposition eines Übergabe- oder Rücknahmeprotokolls.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $report_type
 * @property int $report_id
 * @property string $label
 * @property string $state
 */
class RentalConditionItem extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const STATES = ['ok', 'worn', 'damaged', 'missing'];

    protected $fillable = [
        'organization_id', 'report_type', 'report_id', 'label', 'state', 'note',
    ];

    /** @return MorphTo<Model, $this> */
    public function report(): MorphTo {
        return $this->morphTo();
    }
}
