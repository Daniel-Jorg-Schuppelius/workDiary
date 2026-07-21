<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Security\SecurityEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persistiertes Sicherheitsereignis (Feature 096, MVP-445) — plattformweit,
 * kurze Retention (Pruning im Auswertungslauf `security:evaluate`).
 * Hinweisgeber-Ereignisse landen hier NIE ({@see SecurityEventType::persist}).
 *
 * @property int $id
 * @property SecurityEventType $event
 * @property string|null $ip
 * @property int|null $user_id
 * @property int|null $organization_id
 * @property array<string, mixed>|null $meta
 * @property \Illuminate\Support\Carbon $occurred_at
 */
class SecurityEvent extends Model {
    protected $fillable = [
        'event',
        'ip',
        'user_id',
        'organization_id',
        'meta',
        'occurred_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'event' => SecurityEventType::class,
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
