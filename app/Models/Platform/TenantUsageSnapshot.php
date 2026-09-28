<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantUsageSnapshot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Nutzungsstand eines Mandanten für einen Monat (MVP-956); Betreiberdaten
 * über alle Organisationen, deshalb ohne Mandanten-Scope.
 *
 * @property int $id
 * @property int $organization_id
 * @property Carbon $period_on
 * @property string $plan
 * @property list<string>|null $addons
 * @property int $users
 * @property int|null $active_users
 * @property int $storage_bytes
 * @property int $modules
 * @property numeric-string $amount
 * @property string $currency
 * @property Carbon $computed_at
 */
class TenantUsageSnapshot extends Model {
    protected $fillable = ['organization_id', 'period_on', 'plan', 'addons', 'users', 'active_users', 'storage_bytes', 'modules', 'amount', 'currency', 'computed_at'];

    /** @var array<string, string> */
    protected $casts = [
        'period_on' => 'date',
        'addons' => 'array',
        'users' => 'integer',
        'active_users' => 'integer',
        'storage_bytes' => 'integer',
        'modules' => 'integer',
        'amount' => 'decimal:2',
        'computed_at' => 'datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class)->withoutGlobalScopes();
    }
}
