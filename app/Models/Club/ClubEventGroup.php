<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEventGroup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zielgruppe eines Vereinstermins (MVP-843). Ein Termin kann mehrere
 * Gruppen ansprechen; ein Mitglied aus zwei Zielgruppen erscheint einmal.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property int $club_group_id
 */
class ClubEventGroup extends Model {
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'event_id', 'club_group_id'];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<ClubGroup, $this> */
    public function group(): BelongsTo {
        return $this->belongsTo(ClubGroup::class, 'club_group_id');
    }
}
