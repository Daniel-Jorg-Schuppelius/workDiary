<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeTrackingWebhookDelivery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Zustellprotokoll eines Zeiterfassungs-Webhooks (Feature 124, MVP-613).
 *
 * Bewusst ohne Organisations-Scope: Die Zeile entsteht, BEVOR der Mandant
 * bekannt ist — genau das ist ihr Zweck (Dedup vor Verarbeitung).
 *
 * @property string $plugin_id
 * @property string $delivery_id
 * @property Carbon|null $processed_at
 */
class TimeTrackingWebhookDelivery extends Model {
    protected $fillable = [
        'plugin_id', 'delivery_id', 'event_name', 'organization_id',
        'received_at', 'processed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
