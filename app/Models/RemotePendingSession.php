<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemotePendingSession.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eine Fernwartungs-Verbindung, deren Geräte-ID noch keinem Asset zugeordnet ist.
 * Wird über die Admin-Inbox einem Gerät zugewiesen (Status → imported) oder
 * verworfen (Status → dismissed).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $asset_id
 * @property string $provider
 * @property string $remote_id
 * @property string|null $alias
 * @property string $session_id
 * @property Carbon $started_at
 * @property Carbon $ended_at
 * @property string|null $note
 * @property string $status
 * @property int|null $time_entry_id
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 */
class RemotePendingSession extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    public const STATUS_OPEN = 'open';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_DISMISSED = 'dismissed';

    /**
     * Verbindungsversuch ohne Dauer (start == end, z. B. AnyDesk-Reconnects):
     * wird dokumentiert, aber nie gebucht und nie in der Inbox angeboten.
     */
    public const STATUS_ATTEMPT = 'attempt';

    protected $fillable = [
        'organization_id',
        'asset_id',
        'provider',
        'remote_id',
        'alias',
        'session_id',
        'started_at',
        'ended_at',
        'note',
        'status',
        'time_entry_id',
        'resolved_by',
        'resolved_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /** Dauer in Minuten (für die Inbox-Anzeige). */
    public function minutes(): int {
        $seconds = (int) $this->started_at->diffInSeconds($this->ended_at, absolute: true);

        return $seconds <= 0 ? 0 : max(1, (int) round($seconds / 60));
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<TimeEntry, $this> */
    public function timeEntry(): BelongsTo {
        return $this->belongsTo(TimeEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
