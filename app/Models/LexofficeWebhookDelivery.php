<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeWebhookDelivery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\PrunesWebhookDeliveries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Protokollierte Lexoffice-Webhook-Zustellung (Audit 2026-08, Welle 1.3) —
 * Dedup-Anker VOR der Verarbeitung. Bewusst OHNE Organisations-Scope:
 * die Zeile entsteht im sessionlosen Webhook-Kontext.
 *
 * @property int $id
 * @property string $delivery_hash
 * @property string|null $event_type
 * @property string|null $resource_id
 * @property int|null $organization_id
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 */
class LexofficeWebhookDelivery extends Model {
    use PrunesWebhookDeliveries;

    protected $fillable = [
        'delivery_hash',
        'event_type',
        'resource_id',
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
