<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PollVote.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stimme zu einer Umfrage-Option.
 *
 * @property int $poll_option_id
 * @property int $user_id
 */
class PollVote extends Model {
    protected $table = 'chat_poll_votes';

    protected $fillable = ['poll_option_id', 'user_id'];

    /** @return BelongsTo<PollOption, $this> */
    public function option(): BelongsTo {
        return $this->belongsTo(PollOption::class, 'poll_option_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }
}
