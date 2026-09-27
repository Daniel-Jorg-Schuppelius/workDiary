<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DamageCaseEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Damage;

use App\Models\Journal\JournalEntry;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal eines Schadensfalls (MVP-919), append-only.
 *
 * @property int $id
 * @property int $damage_case_id
 * @property string $event
 * @property int|null $actor_user_id
 * @property array<string, mixed>|null $payload
 * @property \Illuminate\Support\Carbon $created_at
 */
class DamageCaseEvent extends JournalEntry {
    public const UPDATED_AT = null;

    protected $fillable = ['damage_case_id', 'event', 'actor_user_id', 'payload'];

    /** @var array<string, string> */
    protected $casts = ['payload' => 'array', 'created_at' => 'datetime'];

    /** @return BelongsTo<DamageCase, $this> */
    public function subject(): BelongsTo {
        return $this->belongsTo(DamageCase::class, 'damage_case_id');
    }
}
