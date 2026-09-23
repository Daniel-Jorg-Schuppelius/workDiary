<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeSurcharge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Casts\MoneyCast;
use App\Enums\Finance\RecurringInterval;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Abteilungszuschlag (MVP-849): eigene Beitragsposition für Mitglieder, die im
 * Zeitraum aktiv einer Gruppe der Abteilung zugeordnet sind.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_department_id
 * @property string $name
 * @property RecurringInterval $interval
 * @property Money $amount
 * @property CurrencyCode $currency
 * @property int $anchor_month
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 */
class ClubFeeSurcharge extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'club_department_id', 'name', 'interval', 'amount', 'currency', 'anchor_month', 'valid_from', 'valid_to'];

    protected $casts = [
        'interval' => RecurringInterval::class,
        'amount' => MoneyCast::class . ':currency',
        'currency' => CurrencyCode::class,
        'anchor_month' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    /** @return BelongsTo<ClubDepartment, $this> */
    public function department(): BelongsTo {
        return $this->belongsTo(ClubDepartment::class, 'club_department_id');
    }
}
