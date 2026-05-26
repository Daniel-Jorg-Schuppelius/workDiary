<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only Audit-Eintrag für TimeExport (MVP-019).
 *
 * @property int $id
 * @property int $time_export_id
 * @property string $event
 * @property int|null $actor_user_id
 * @property string|null $note
 * @property array<string, mixed>|null $payload
 * @property Carbon $created_at
 */
class TimeExportEvent extends Model {
    public const UPDATED_AT = null;

    protected $fillable = [
        'time_export_id',
        'event',
        'actor_user_id',
        'note',
        'payload',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<TimeExport, $this> */
    public function export(): BelongsTo {
        return $this->belongsTo(TimeExport::class, 'time_export_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
