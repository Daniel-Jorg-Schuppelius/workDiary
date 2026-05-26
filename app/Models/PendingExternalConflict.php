<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PendingExternalConflict.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $plugin_id
 * @property string $conflict_type
 * @property string $referenceable_type
 * @property int $referenceable_id
 * @property string|null $external_id
 * @property array<string, mixed> $local_snapshot
 * @property array<string, mixed> $remote_snapshot
 * @property array<int, string>|null $diff_fields
 * @property string $status
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 */
class PendingExternalConflict extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED_LOCAL = 'resolved_local';

    public const STATUS_RESOLVED_REMOTE = 'resolved_remote';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'organization_id',
        'plugin_id',
        'conflict_type',
        'referenceable_type',
        'referenceable_id',
        'external_id',
        'local_snapshot',
        'remote_snapshot',
        'diff_fields',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'local_snapshot' => 'array',
        'remote_snapshot' => 'array',
        'diff_fields' => 'array',
        'resolved_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function referenceable(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool {
        return $this->status === self::STATUS_OPEN;
    }
}
