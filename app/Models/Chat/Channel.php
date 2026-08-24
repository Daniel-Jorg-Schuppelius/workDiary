<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Channel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Chat;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

/**
 * Chat-Kanal: benannter Kanal (public/private), Gruppe oder Direktnachricht.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $name
 * @property string $type        channel|group|direct
 * @property string $visibility  public|private
 * @property bool $is_archived
 */
class Channel extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Chat\ChannelFactory> */
    use HasFactory;
    use HasSqid;

    protected $table = 'chat_channels';

    protected $fillable = [
        'organization_id', 'name', 'slug', 'description',
        'type', 'visibility', 'is_archived', 'created_by',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
    ];

    public const TYPES = ['channel', 'group', 'direct'];
    public const VISIBILITIES = ['public', 'private'];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany {
        return $this->hasMany(Message::class, 'channel_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany {
        return $this->belongsToMany(User::class, 'chat_channel_user', 'channel_id', 'user_id')
            ->withPivot(['role', 'last_read_at', 'muted_at', 'joined_at'])
            ->withTimestamps();
    }

    public function isDirect(): bool {
        return $this->type === 'direct';
    }

    public function isPrivate(): bool {
        return $this->visibility === 'private' || $this->type !== 'channel';
    }

    /** Ist der Benutzer Mitglied dieses Kanals? */
    public function hasMember(User $user): bool {
        return $this->members()->whereKey($user->id)->exists();
    }

    /** Ist der Benutzer Eigentümer (Rolle owner) dieses Kanals? */
    public function isOwner(User $user): bool {
        return $this->members()->whereKey($user->id)->wherePivot('role', 'owner')->exists();
    }

    /**
     * Gesamtzahl ungelesener Top-Level-Nachrichten über alle Kanäle des Benutzers
     * (für das Header-Badge) — eine Query, org-gescopt.
     */
    public static function unreadTotalFor(User $user): int {
        return Message::query()
            ->whereNull('parent_id')
            ->where('chat_messages.user_id', '!=', $user->id)
            ->join('chat_channel_user as cu', function ($j) use ($user): void {
                $j->on('cu.channel_id', '=', 'chat_messages.channel_id')->where('cu.user_id', '=', $user->id);
            })
            ->where(function ($q): void {
                $q->whereNull('cu.last_read_at')->orWhereColumn('chat_messages.created_at', '>', 'cu.last_read_at');
            })
            ->count();
    }

    /** Ungelesene Nachrichten für den Benutzer (seit last_read_at, ohne eigene). */
    public function unreadCountFor(User $user): int {
        $membership = \Illuminate\Support\Facades\DB::table('chat_channel_user')
            ->where('channel_id', $this->id)
            ->where('user_id', $user->id)
            ->first(['last_read_at']);
        if ($membership === null) {
            return 0;
        }
        $lastRead = $membership->last_read_at;

        return $this->messages()
            ->whereNull('parent_id')
            ->where('user_id', '!=', $user->id)
            ->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))
            ->count();
    }
}
