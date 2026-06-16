<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectPendingEntry.php
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
 * Ein aus OpenProject importierter Zeiteintrag, dessen Projekt noch keinem
 * workDiary-Projekt zugeordnet ist. Wird über die Admin-Inbox einem bestehenden
 * Projekt zugewiesen (Status → imported) oder verworfen (Status → dismissed).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $entry_key
 * @property string|null $project_external_id
 * @property string|null $project_name
 * @property string|null $work_package_external_id
 * @property string|null $work_package_subject
 * @property string|null $description
 * @property Carbon $spent_on
 * @property int $minutes
 * @property string|null $user_external_id
 * @property string|null $user_name
 * @property string $status
 * @property int|null $time_entry_id
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 */
class OpenProjectPendingEntry extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'openproject_pending_entries';

    public const STATUS_OPEN = 'open';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'organization_id',
        'entry_key',
        'project_external_id',
        'project_name',
        'work_package_external_id',
        'work_package_subject',
        'description',
        'spent_on',
        'minutes',
        'user_external_id',
        'user_name',
        'status',
        'time_entry_id',
        'resolved_by',
        'resolved_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'spent_on' => 'date',
        'minutes' => 'integer',
        'resolved_at' => 'datetime',
    ];

    /** @return BelongsTo<TimeEntry, $this> */
    public function timeEntry(): BelongsTo {
        return $this->belongsTo(TimeEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
