<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PollOption.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Antwortoption einer Umfrage.
 *
 * @property int $poll_id
 * @property string $label
 * @property int $position
 */
class PollOption extends Model {
    protected $table = 'chat_poll_options';

    protected $fillable = ['poll_id', 'label', 'position'];

    /** @return BelongsTo<Poll, $this> */
    public function poll(): BelongsTo {
        return $this->belongsTo(Poll::class, 'poll_id');
    }

    /** @return HasMany<PollVote, $this> */
    public function votes(): HasMany {
        return $this->hasMany(PollVote::class, 'poll_option_id');
    }
}
