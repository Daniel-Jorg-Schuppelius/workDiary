<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyWebhookDelivery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persist-before-process-Deduplizierung der Etsy-Webhooks (Feature 101,
 * MVP-496): Dedupe über den Body-Hash (deckt auch Portal-Replays ab);
 * die Svix-`webhook-id` wird zusätzlich zur Diagnose gespeichert.
 *
 * @property int $id
 * @property string $delivery_hash
 * @property string|null $webhook_id
 * @property string|null $event_type
 * @property int|null $receipt_id
 * @property int|null $organization_id
 * @property \Illuminate\Support\Carbon $received_at
 * @property \Illuminate\Support\Carbon|null $processed_at
 */
class EtsyWebhookDelivery extends Model {
    protected $fillable = [
        'delivery_hash',
        'webhook_id',
        'event_type',
        'receipt_id',
        'organization_id',
        'received_at',
        'processed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'receipt_id' => 'integer',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
