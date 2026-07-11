<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimInspection.php
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
 * Prüfergebnis eines Rückläufers (MVP-250) inkl. Seriennummernprüfung
 * (wurde die Nummer je an diesen Kunden geliefert? → SerialService).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $claim_rma_return_id
 * @property string $result
 * @property string|null $findings
 * @property bool $serial_checked
 * @property string|null $serial_check_result
 * @property \Illuminate\Support\Carbon $inspected_at
 */
class ClaimInspection extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const RESULTS = ['defect_confirmed', 'no_defect_found', 'misuse', 'transport_damage', 'inconclusive'];

    protected $fillable = [
        'organization_id', 'claim_rma_return_id', 'result', 'findings',
        'serial_checked', 'serial_check_result', 'inspected_by', 'inspected_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'serial_checked' => 'boolean',
        'inspected_at' => 'datetime',
    ];

    /** @return BelongsTo<ClaimRmaReturn, $this> */
    public function rmaReturn(): BelongsTo {
        return $this->belongsTo(ClaimRmaReturn::class, 'claim_rma_return_id');
    }

    /** @return BelongsTo<User, $this> */
    public function inspector(): BelongsTo {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
