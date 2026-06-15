<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryEntryEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $diary_entry_id
 * @property int $organization_id
 * @property string $event
 * @property string|null $from_status
 * @property string $to_status
 * @property int|null $actor_user_id
 * @property string $actor_kind
 * @property string|null $note
 * @property array<string, mixed>|null $payload
 * @property \Illuminate\Support\Carbon $occurred_at
 */
class DiaryEntryEvent extends Model {
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $fillable = [
        'diary_entry_id',
        'organization_id',
        'event',
        'from_status',
        'to_status',
        'actor_user_id',
        'actor_kind',
        'note',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'immutable_datetime',
    ];

    protected static function booted(): void {
        static::updating(static function (): never {
            throw new LogicException('Lebenszyklusereignisse dürfen nicht geändert werden.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Lebenszyklusereignisse dürfen nicht gelöscht werden.');
        });
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
