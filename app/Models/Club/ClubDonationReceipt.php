<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubDonationReceipt.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Casts\MoneyCast;
use App\Enums\Club\ClubDonationReceiptKind;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Zuwendungsbestätigung (MVP-1003) mit eingefrorenen Angaben zu Zuwendendem und
 * Freistellung; das PDF entsteht aus diesem Stand, spätere Stammdaten deuten es
 * nicht um.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $receipt_no
 * @property int $year
 * @property ClubDonationReceiptKind $kind
 * @property int|null $club_member_id
 * @property array{name: string, address: list<string>} $donor_snapshot
 * @property array<string, string|bool|null> $exemption_snapshot
 * @property Money $total_amount
 * @property CurrencyCode $currency
 * @property Carbon $issued_on
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ClubDonationReceipt extends Model {
    use AppendOnly;
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'receipt_no',
        'year',
        'kind',
        'club_member_id',
        'donor_snapshot',
        'exemption_snapshot',
        'total_amount',
        'currency',
        'issued_on',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'receipt_no' => 'integer',
        'year' => 'integer',
        'kind' => ClubDonationReceiptKind::class,
        'donor_snapshot' => 'array',
        'exemption_snapshot' => 'array',
        'total_amount' => MoneyCast::class . ':currency,2',
        'currency' => CurrencyCode::class,
        'issued_on' => 'date',
    ];

    /** @return HasMany<ClubDonation, $this> */
    public function donations(): HasMany {
        return $this->hasMany(ClubDonation::class)->orderBy('received_on');
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    public function displayNo(): string {
        return 'ZB-' . $this->year . '-' . $this->receipt_no;
    }
}
