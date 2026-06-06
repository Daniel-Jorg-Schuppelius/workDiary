<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MessageReaction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reaktion auf eine Nachricht (Emoji; 👍 = "Like").
 *
 * @property int $message_id
 * @property int $user_id
 * @property string $emoji
 */
class MessageReaction extends Model {
    protected $table = 'chat_message_reactions';

    protected $fillable = ['message_id', 'user_id', 'emoji'];

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo {
        return $this->belongsTo(Message::class, 'message_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }
}
