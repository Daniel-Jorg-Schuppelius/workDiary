<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventReminder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Event\ReminderChannel;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $event_id
 * @property int|null $user_id  null = an alle Teilnehmer
 * @property Carbon $remind_at
 * @property ReminderChannel $channel
 * @property Carbon|null $sent_at
 * @property string|null $error
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EventReminder extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'event_id',
        'user_id',
        'remind_at',
        'channel',
        'sent_at',
        'error',
        'payload',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'remind_at' => 'datetime',
        'sent_at' => 'datetime',
        'channel' => ReminderChannel::class,
        'payload' => 'array',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<EventReminder> $query */
    public function scopeDue(Builder $query, ?Carbon $now = null): void {
        $query->whereNull('sent_at')
            ->where('remind_at', '<=', $now ?? now());
    }
}
