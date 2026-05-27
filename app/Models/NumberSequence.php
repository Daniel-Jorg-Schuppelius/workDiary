<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberSequence.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Numbering\NumberScope;
use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Database\Factories\NumberSequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property NumberScope $scope
 * @property string|null $period
 * @property int $last_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NumberSequence extends Model {
    /** @use HasFactory<NumberSequenceFactory> */
    use Auditable, BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'scope',
        'period',
        'last_value',
    ];

    protected $casts = [
        'scope' => NumberScope::class,
        'last_value' => 'int',
    ];
}
