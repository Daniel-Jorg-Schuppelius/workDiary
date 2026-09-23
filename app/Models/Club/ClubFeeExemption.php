<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeExemption.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubFeeExemptionKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Befreiung oder Ermäßigung mit Zeitraum und Grund (MVP-849) — ausdrücklich
 * erfasst; eine Mitgliedschaftspause allein erlässt keinen Beitrag.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_member_id
 * @property ClubFeeExemptionKind $kind
 * @property string|null $percent
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string $reason
 * @property int|null $created_by_user_id
 */
class ClubFeeExemption extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'club_member_id', 'kind', 'percent', 'starts_on', 'ends_on', 'reason', 'created_by_user_id'];

    protected $casts = ['kind' => ClubFeeExemptionKind::class, 'starts_on' => 'date', 'ends_on' => 'date'];

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Anteil, der an einem Tag zu zahlen bleibt, in Prozent (0 = befreit, 100 = voll). */
    public function payablePercent(): string {
        if ($this->kind === ClubFeeExemptionKind::Exemption) {
            return '0';
        }
        return Decimal::of('100', 2)->minus(Decimal::of((string) ($this->percent ?? '0'), 2))->getValue();
    }
}
