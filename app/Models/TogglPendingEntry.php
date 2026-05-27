<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglPendingEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ein aus Toggl importierter Zeiteintrag, dessen Client/Projekt noch keinem
 * workDiary-Kunden bzw. -Projekt zugeordnet ist. Wird über die Admin-Inbox
 * einem bestehenden Kunden + Projekt zugewiesen (Status → imported) oder
 * verworfen (Status → dismissed).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $source
 * @property string $entry_key
 * @property string|null $client_name
 * @property string|null $project_name
 * @property string|null $description
 * @property Carbon $started_at
 * @property Carbon $ended_at
 * @property bool $billable
 * @property string|null $user_email
 * @property string $status
 * @property int|null $time_entry_id
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 */
class TogglPendingEntry extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const SOURCE_API = 'api';

    public const SOURCE_CSV = 'csv';

    public const STATUS_OPEN = 'open';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'organization_id',
        'source',
        'entry_key',
        'client_name',
        'project_name',
        'description',
        'started_at',
        'ended_at',
        'billable',
        'user_email',
        'status',
        'time_entry_id',
        'resolved_by',
        'resolved_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'billable' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    /** Dauer in Minuten (für die Inbox-Anzeige). */
    public function minutes(): int {
        $seconds = (int) $this->started_at->diffInSeconds($this->ended_at, absolute: true);

        return $seconds <= 0 ? 0 : max(1, (int) round($seconds / 60));
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
