<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequestEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Models\Concerns\{HashChainable, HashChained};
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only Ereignisprotokoll fuer Betroffenenanfragen — minimierte Metadaten
 * ohne Klartext-PII, abgesichert ueber die {@see HashChained}-Hash-Kette
 * (registriert in config('audit.chains'), pruefbar via `audit:verify`). Ohne
 * BelongsToOrganization: die Kette muss scope-frei verifizierbar sein.
 *
 * @property string|null $prev_hash
 * @property string|null $hash
 *
 * @phpstan-consistent-constructor
 */
class RequestEvent extends Model implements HashChainable {
    use HashChained;

    protected $table = 'privacy_request_events';

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'request_id',
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

    /**
     * In den Hash eingehende Nutzdaten (feste Reihenfolge, IDs normalisiert).
     *
     * @return array<string, mixed>
     */
    public function hashPayload(): array {
        return [
            'organization_id' => $this->nullableInt($this->getAttribute('organization_id')),
            'request_id' => $this->nullableInt($this->getAttribute('request_id')),
            'actor_type' => $this->getAttribute('actor_type'),
            'actor_user_id' => $this->nullableInt($this->getAttribute('actor_user_id')),
            'event' => $this->getAttribute('event'),
            'metadata' => $this->getAttribute('metadata'),
            'created_at' => $this->hashCreatedAt(),
        ];
    }

    private function nullableInt(mixed $value): ?int {
        return $value === null ? null : (int) $value;
    }
}
