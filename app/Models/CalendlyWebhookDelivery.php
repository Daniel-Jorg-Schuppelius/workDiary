<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyWebhookDelivery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\PrunesWebhookDeliveries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Protokollierte Calendly-Webhook-Zustellung (Feature 095) — Dedup-Anker VOR
 * der Verarbeitung. Calendly liefert keine Delivery-ID, daher ist
 * `delivery_hash` = sha256(rawBody); der Unique-Constraint macht Replays
 * idempotent. Bewusst OHNE Organisations-Scope (die Zeile ist ein
 * Betriebsprotokoll).
 *
 * @property int $id
 * @property string $delivery_hash
 * @property string|null $event_name
 * @property string|null $invitee_uri
 * @property int|null $organization_id
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 */
class CalendlyWebhookDelivery extends Model {
    use PrunesWebhookDeliveries;

    protected $fillable = [
        'delivery_hash',
        'event_name',
        'invitee_uri',
        'organization_id',
        'received_at',
        'processed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
