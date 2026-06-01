<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberFormat.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Numbering\NumberScope;
use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Database\Factories\NumberFormatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property NumberScope $scope
 * @property string $source
 * @property string $prefix
 * @property string $prefix_separator
 * @property bool $include_year
 * @property string $year_separator
 * @property int $padding
 * @property bool $reset_per_year
 * @property int $starts_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NumberFormat extends Model {
    /** @use HasFactory<NumberFormatFactory> */
    use Auditable, BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'scope',
        'source',
        'prefix',
        'prefix_separator',
        'include_year',
        'year_separator',
        'padding',
        'reset_per_year',
        'starts_at',
    ];

    protected $casts = [
        'scope' => NumberScope::class,
        'include_year' => 'bool',
        'reset_per_year' => 'bool',
        'padding' => 'int',
        'starts_at' => 'int',
    ];
}
