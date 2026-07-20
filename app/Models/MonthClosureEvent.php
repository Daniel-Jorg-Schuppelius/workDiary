<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthClosureEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only Statuswechsel-Audit für eine MonthClosure (MVP-016).
 *
 * @property int $id
 * @property int $month_closure_id
 * @property string $event
 * @property int $actor_user_id
 * @property string|null $note
 * @property array<string, mixed>|null $payload
 * @property Carbon $created_at
 */
class MonthClosureEvent extends Model {
    // Append-only jetzt technisch erzwungen statt nur dokumentiert (Vollaudit 2026-07, M52).
    use AppendOnly;

    public const UPDATED_AT = null;

    protected $fillable = [
        'month_closure_id',
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

    /** @return BelongsTo<MonthClosure, $this> */
    public function monthClosure(): BelongsTo {
        return $this->belongsTo(MonthClosure::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
