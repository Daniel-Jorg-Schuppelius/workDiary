<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Reminder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Erinnerung an eine Chat-Nachricht. Pro-Nutzer, mandantenneutral
 * (Mandantengrenze ergibt sich über message_id/channel_id).
 *
 * @property int $id
 * @property int $user_id
 * @property int $message_id
 * @property int $channel_id
 * @property \Illuminate\Support\Carbon $remind_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 */
class Reminder extends Model {
    protected $table = 'chat_reminders';

    protected $fillable = ['user_id', 'message_id', 'channel_id', 'remind_at', 'sent_at'];

    protected $casts = [
        'remind_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo {
        return $this->belongsTo(Message::class, 'message_id');
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
}
