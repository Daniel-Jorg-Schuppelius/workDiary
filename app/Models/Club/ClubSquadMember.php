<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubSquadMember.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Kaderzuordnung (MVP-852): Mitglied oder Gastspieler (Herkunftsverein) mit
 * Gültigkeit, Trikotnummer, Position und Spielstärke-Reihenfolge.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_squad_id
 * @property int $club_member_id
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property string|null $guest_origin
 * @property int|null $jersey_no
 * @property string|null $position_code
 * @property int|null $strength_rank
 * @property string|null $notes
 */
class ClubSquadMember extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'club_squad_id', 'club_member_id', 'valid_from', 'valid_to', 'guest_origin',
        'jersey_no', 'position_code', 'strength_rank', 'notes',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'jersey_no' => 'integer',
        'strength_rank' => 'integer',
    ];

    /** @return BelongsTo<ClubSquad, $this> */
    public function squad(): BelongsTo {
        return $this->belongsTo(ClubSquad::class, 'club_squad_id');
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    public function isGuest(): bool {
        return $this->guest_origin !== null && $this->guest_origin !== '';
    }

    public function isValidOn(CarbonInterface $date): bool {
        return ! $date->lt($this->valid_from->startOfDay()) && ($this->valid_to === null || ! $date->gt($this->valid_to->endOfDay()));
    }
}
