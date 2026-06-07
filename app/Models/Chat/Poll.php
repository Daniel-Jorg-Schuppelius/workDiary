<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Poll.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Chat;

use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Umfrage an einer Nachricht.
 *
 * @property int $message_id
 * @property string $question
 * @property bool $multiple
 * @property \Illuminate\Support\Carbon|null $closes_at
 */
class Poll extends Model {
    use HasSqid;

    protected $table = 'chat_polls';

    protected $fillable = ['message_id', 'question', 'multiple', 'closes_at'];

    protected $casts = [
        'multiple' => 'boolean',
        'closes_at' => 'datetime',
    ];

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo {
        return $this->belongsTo(Message::class, 'message_id');
    }

    /** @return HasMany<PollOption, $this> */
    public function options(): HasMany {
        return $this->hasMany(PollOption::class, 'poll_id')->orderBy('position');
    }

    public function isClosed(): bool {
        return $this->closes_at !== null && $this->closes_at->isPast();
    }
}
