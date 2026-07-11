<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimReportSnapshot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Claims;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eingefrorener Berichtsstand (MVP-254): Quoten/Ursachen/Kosten je
 * Periode als Nachweis — spätere Falländerungen ändern den Snapshot nicht.
 *
 * @property int $id
 * @property int $organization_id
 * @property \Illuminate\Support\Carbon $period_start
 * @property \Illuminate\Support\Carbon $period_end
 * @property array<string, mixed> $payload
 */
class ClaimReportSnapshot extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'period_start', 'period_end', 'payload', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'payload' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }
}
