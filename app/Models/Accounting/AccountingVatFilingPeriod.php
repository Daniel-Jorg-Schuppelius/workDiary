<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVatFilingPeriod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Finance\VatFilingInterval;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abschnitt eines Voranmeldungszeitraums (Feature 125, MVP-684).
 *
 * @property VatFilingInterval $interval
 */
class AccountingVatFilingPeriod extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'interval',
        'valid_from',
        'valid_to',
        'reason',
        'actor_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'interval' => VatFilingInterval::class,
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
