<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeDunning.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Casts\MoneyCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Mahnstufe einer Beitragsforderung (MVP-851): Historie mit Zahlungsziel und
 * optionaler Gebühr, die als verknüpfte Korrekturforderung entsteht — nie
 * ungeprüft aus allgemeinen Rechnungsdefaults.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_fee_claim_id
 * @property int $level
 * @property Carbon $issued_on
 * @property Carbon|null $pay_until
 * @property Money|null $fee
 * @property CurrencyCode $currency
 * @property int|null $fee_claim_id
 * @property string|null $note
 * @property int|null $created_by_user_id
 */
class ClubFeeDunning extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'club_fee_claim_id', 'level', 'issued_on', 'pay_until', 'fee', 'currency', 'fee_claim_id', 'note', 'created_by_user_id'];

    protected $casts = [
        'level' => 'integer',
        'issued_on' => 'date',
        'pay_until' => 'date',
        'fee' => MoneyCast::class . ':currency',
        'currency' => CurrencyCode::class,
    ];

    /** @return BelongsTo<ClubFeeClaim, $this> */
    public function claim(): BelongsTo {
        return $this->belongsTo(ClubFeeClaim::class, 'club_fee_claim_id');
    }

    /** @return BelongsTo<ClubFeeClaim, $this> */
    public function feeClaim(): BelongsTo {
        return $this->belongsTo(ClubFeeClaim::class, 'fee_claim_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
