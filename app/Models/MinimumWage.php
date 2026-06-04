<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MinimumWage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\MinimumWageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ein gültiger Mindestlohn-Satz ab einem Stichtag (Gültig-ab-Historie).
 *
 * @property int $id
 * @property int $organization_id
 * @property Carbon $valid_from
 * @property string $hourly_amount
 * @property string|null $note
 * @property int|null $created_by
 */
class MinimumWage extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<MinimumWageFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'valid_from',
        'hourly_amount',
        'note',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'valid_from' => 'date',
        'hourly_amount' => 'decimal:2',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }
}
