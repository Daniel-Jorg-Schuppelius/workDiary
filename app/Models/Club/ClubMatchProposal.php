<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMatchProposal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\{ClubMatchProposalSource, ClubProposalStatus};
use App\Models\Calendar\Event;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Spielplan-Vorschlag aus CSV/ICS (MVP-852): ändert vor der Bestätigung
 * nichts; die Leitung prüft Gegner, Ort und Zeit, Dubletten werden markiert.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_group_id
 * @property ClubMatchProposalSource $source
 * @property string $dedupe_key
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property string $opponent_name
 * @property string|null $competition
 * @property bool $is_home
 * @property string|null $venue
 * @property array<string, mixed>|null $raw
 * @property ClubProposalStatus $status
 * @property int|null $event_id
 * @property int|null $duplicate_event_id
 * @property int|null $imported_by_user_id
 * @property int|null $decided_by_user_id
 * @property Carbon|null $decided_at
 */
class ClubMatchProposal extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'club_group_id', 'source', 'dedupe_key', 'starts_at', 'ends_at', 'opponent_name', 'competition', 'is_home', 'venue',
        'raw', 'status', 'event_id', 'duplicate_event_id', 'imported_by_user_id', 'decided_by_user_id', 'decided_at',
    ];

    protected $casts = [
        'source' => ClubMatchProposalSource::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_home' => 'boolean',
        'raw' => 'array',
        'status' => ClubProposalStatus::class,
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<ClubGroup, $this> */
    public function team(): BelongsTo {
        return $this->belongsTo(ClubGroup::class, 'club_group_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function duplicateEvent(): BelongsTo {
        return $this->belongsTo(Event::class, 'duplicate_event_id');
    }

    /** @return BelongsTo<User, $this> */
    public function importedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }

    public function isOpen(): bool {
        return $this->status === ClubProposalStatus::Open;
    }
}
