<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $protocol_id
 * @property string $event
 * @property int $actor_user_id
 * @property array<string, mixed>|null $payload
 * @property \Illuminate\Support\Carbon $created_at
 */
class ProtocolEvent extends Model {
    // Append-only jetzt technisch erzwungen statt nur dokumentiert (Vollaudit 2026-07, M52).
    use AppendOnly;

    public $timestamps = false;

    protected $fillable = [
        'protocol_id',
        'event',
        'actor_user_id',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Protocol, $this> */
    public function protocol(): BelongsTo {
        return $this->belongsTo(Protocol::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
