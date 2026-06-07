<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledMessage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Geplante (noch nicht gesendete) Chat-Nachricht. Mandantenneutral –
 * die Grenze ergibt sich über channel_id/user_id.
 *
 * @property int $id
 * @property int $channel_id
 * @property int $user_id
 * @property string|null $body
 * @property \Illuminate\Support\Carbon $scheduled_at
 */
class ScheduledMessage extends Model {
    protected $table = 'chat_scheduled_messages';

    protected $fillable = ['channel_id', 'user_id', 'body', 'scheduled_at'];

    protected $casts = ['scheduled_at' => 'datetime'];

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }
}
