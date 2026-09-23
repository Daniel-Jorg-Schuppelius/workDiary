<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubLineupEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubLineupSlot;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aufstellungsposition (MVP-852): Feld/Bank mit Position und Trikotnummer
 * oder Einzel/Doppel mit Paarungsnummer; eine Person je Platzschlüssel.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property int $club_member_id
 * @property ClubLineupSlot $slot
 * @property string $slot_key
 * @property string|null $position_code
 * @property int|null $jersey_no
 * @property int $order_no
 * @property int|null $pairing_no
 */
class ClubLineupEntry extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'event_id', 'club_member_id', 'slot', 'slot_key', 'position_code', 'jersey_no', 'order_no', 'pairing_no'];

    protected $casts = ['slot' => ClubLineupSlot::class, 'jersey_no' => 'integer', 'order_no' => 'integer', 'pairing_no' => 'integer'];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }
}
