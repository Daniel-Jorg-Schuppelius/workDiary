<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Diary;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Journal\JournalEntry;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal der Tour (MVP-1176): Vorschlag berechnet, übernommen, verworfen,
 * Tour gestartet, beendet, Fahrten übernommen, Stopp-Ist.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $tour_id
 * @property string $event
 * @property int|null $actor_user_id
 * @property array<string, mixed>|null $payload
 * @property \Illuminate\Support\Carbon $occurred_at
 */
class TourEvent extends JournalEntry {
    use BelongsToOrganization;

    /** @var array<string, string|null> */
    protected static array $journalColumns = ['occurred_at' => 'occurred_at'];

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'tour_id',
        'event',
        'actor_user_id',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo {
        return $this->belongsTo(Tour::class);
    }

    /** @return BelongsTo<Tour, $this> */
    public function subject(): BelongsTo {
        return $this->tour();
    }
}
