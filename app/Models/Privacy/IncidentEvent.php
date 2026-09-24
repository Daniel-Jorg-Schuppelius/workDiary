<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncidentEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Models\Journal\HashChainedJournalEntry;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only Ereignisprotokoll fuer Datenschutzvorfaelle (Hash-Kette, in
 * config('audit.chains'), pruefbar via `audit:verify`).
 *
 * @property string|null $prev_hash
 * @property string|null $hash
 *
 * @phpstan-consistent-constructor
 */
class IncidentEvent extends HashChainedJournalEntry {
    /** @var array<string, string|null> */
    protected static array $journalColumns = ['payload' => 'metadata'];

    protected $table = 'privacy_incident_events';

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'incident_id',
        'actor_type',
        'actor_user_id',
        'event',
        'metadata',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return array<string, mixed> */
    public function hashPayload(): array {
        return [
            'organization_id' => $this->nullableInt($this->getAttribute('organization_id')),
            'incident_id' => $this->nullableInt($this->getAttribute('incident_id')),
            'actor_type' => $this->getAttribute('actor_type'),
            'actor_user_id' => $this->nullableInt($this->getAttribute('actor_user_id')),
            'event' => $this->getAttribute('event'),
            'metadata' => $this->getAttribute('metadata'),
            'created_at' => $this->hashCreatedAt(),
        ];
    }

    /** @return BelongsTo<Incident, $this> */
    public function subject(): BelongsTo {
        return $this->belongsTo(Incident::class, 'incident_id');
    }
}
