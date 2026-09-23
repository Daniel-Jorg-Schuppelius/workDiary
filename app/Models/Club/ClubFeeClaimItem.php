<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeClaimItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToOrganization;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Position einer Beitragsforderung (MVP-850) mit fachlichem Quellschlüssel —
 * je Organisation nur einmal aktiv (Unique); Storno benennt ihn um.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_fee_claim_id
 * @property int|null $club_member_id
 * @property string $kind
 * @property string $source_key
 * @property string $label
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Money $amount
 * @property CurrencyCode $currency
 * @property array<string, mixed>|null $basis
 */
class ClubFeeClaimItem extends Model {
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'club_fee_claim_id', 'club_member_id', 'kind', 'source_key', 'label', 'period_start', 'period_end', 'amount', 'currency', 'basis'];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'amount' => MoneyCast::class . ':currency',
        'currency' => CurrencyCode::class,
        'basis' => 'array',
    ];

    /** @return BelongsTo<ClubFeeClaim, $this> */
    public function claim(): BelongsTo {
        return $this->belongsTo(ClubFeeClaim::class, 'club_fee_claim_id');
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }
}
