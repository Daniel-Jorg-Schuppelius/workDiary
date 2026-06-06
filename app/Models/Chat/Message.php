<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Message.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Chat;

use App\Models\Attachment;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne, MorphMany};
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Chat-Nachricht. parent_id != null → Thread-Antwort (Kommentar).
 *
 * @property int $id
 * @property int $channel_id
 * @property int|null $user_id
 * @property int|null $parent_id
 * @property string|null $body
 * @property string $type   text|poll|system
 * @property \Illuminate\Support\Carbon|null $pinned_at
 * @property \Illuminate\Support\Carbon|null $edited_at
 */
class Message extends Model {
    use BelongsToOrganization;
    use SoftDeletes;

    protected $table = 'chat_messages';

    protected $fillable = [
        'organization_id', 'channel_id', 'user_id', 'parent_id',
        'body', 'type', 'pinned_at', 'pinned_by', 'edited_at',
    ];

    protected $casts = [
        'pinned_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Message, $this> */
    public function parent(): BelongsTo {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Message, $this> Thread-Antworten (Kommentare). */
    public function replies(): HasMany {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<MessageReaction, $this> */
    public function reactions(): HasMany {
        return $this->hasMany(MessageReaction::class, 'message_id');
    }

    /** @return MorphMany<Attachment, $this> Bilder/Dateien (polymorph). */
    public function attachments(): MorphMany {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** @return HasOne<Poll, $this> */
    public function poll(): HasOne {
        return $this->hasOne(Poll::class, 'message_id');
    }

    public function isPinned(): bool {
        return $this->pinned_at !== null;
    }
}
