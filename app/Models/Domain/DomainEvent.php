<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Domain;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Durable-Store eines Provider-Ereignisses aus `QueryEventList` (Feature 083,
 * MVP-391). Event-ID/Status/Rohhash werden DAUERHAFT gespeichert, BEVOR
 * `DeleteEvent` als Acknowledge gesendet wird — kein Datenverlust bei Abbruch.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $connection_id
 * @property string $external_event_id
 * @property string|null $event_class
 * @property string|null $event_action
 * @property string|null $object
 * @property string $status
 * @property string $raw_hash
 * @property Carbon|null $occurred_at
 * @property Carbon|null $stored_at
 * @property Carbon|null $acknowledged_at
 */
class DomainEvent extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'connection_id',
        'external_event_id',
        'event_class',
        'event_action',
        'object',
        'status',
        'raw_hash',
        'occurred_at',
        'stored_at',
        'acknowledged_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'occurred_at' => 'datetime',
        'stored_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function isAcknowledged(): bool {
        return $this->status === 'acknowledged';
    }

    /** @return BelongsTo<DomainProviderConnection, $this> */
    public function connection(): BelongsTo {
        return $this->belongsTo(DomainProviderConnection::class, 'connection_id');
    }
}
