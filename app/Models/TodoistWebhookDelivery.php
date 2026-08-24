<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistWebhookDelivery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\PrunesWebhookDeliveries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Protokollierte Todoist-Webhook-Zustellung (Feature 055, MVP-115) —
 * Dedup-Anker VOR der Verarbeitung. Bewusst OHNE Organisations-Scope:
 * die Zeile entsteht vor der Org-Zuordnung (erst nach Signaturprüfung).
 *
 * @property int $id
 * @property string $delivery_id
 * @property string|null $event_name
 * @property int|null $organization_id
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 */
class TodoistWebhookDelivery extends Model {
    use PrunesWebhookDeliveries;

    protected $fillable = [
        'delivery_id',
        'event_name',
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
