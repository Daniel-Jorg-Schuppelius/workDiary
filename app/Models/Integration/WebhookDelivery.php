<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookDelivery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Integration;

use App\Enums\Integration\WebhookDeliveryStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zustellprotokoll-Eintrag eines Webhook-Auslieferungsversuchs (Feature 008).
 *
 * Speichert bewusst nur den payload_hash, nicht den (ggf. personenbezogenen)
 * Payload-Inhalt — das Protokoll dient der Diagnose (Status/HTTP-Code), nicht
 * der Datenhaltung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $webhook_endpoint_id
 * @property string $event
 * @property string $payload_hash
 * @property WebhookDeliveryStatus $status
 * @property int|null $http_status
 * @property int $attempt
 * @property string|null $response_excerpt
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WebhookDelivery extends Model {
    /** @use HasFactory<\Database\Factories\Integration\WebhookDeliveryFactory> */
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'webhook_endpoint_id',
        'event',
        'payload_hash',
        'status',
        'http_status',
        'attempt',
        'response_excerpt',
        'dispatched_at',
        'completed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => WebhookDeliveryStatus::class,
        'http_status' => 'integer',
        'attempt' => 'integer',
        'dispatched_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<WebhookEndpoint, $this> */
    public function endpoint(): BelongsTo {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
