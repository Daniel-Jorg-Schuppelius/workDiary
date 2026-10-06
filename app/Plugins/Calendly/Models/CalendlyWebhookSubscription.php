<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyWebhookSubscription.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Calendly\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Plugins\Calendly\Enums\CalendlyWebhookSubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Calendly-Webhook-Subscription (Feature 095): der opake `url_token` in der
 * Callback-URL löst diese Zeile in O(1) auf → `organization_id` +
 * `signing_key`. Die Organisation wird NIE aus dem Payload geraten
 * (Mandantensicherheit). `signing_key` ist verschlüsselt at-rest.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $calendly_connection_id
 * @property string $url_token
 * @property string $signing_key
 * @property string|null $calendly_subscription_uri
 * @property string $scope
 * @property array<int, string> $events
 * @property CalendlyWebhookSubscriptionStatus $status
 * @property Carbon|null $last_delivery_at
 */
class CalendlyWebhookSubscription extends Model {
    use BelongsToOrganization;

    public const SCOPE_ORGANIZATION = 'organization';

    public const SCOPE_USER = 'user';

    protected $table = 'calendly_webhook_subscriptions';

    /** Signing-Key nie serialisieren. */
    protected $hidden = [
        'signing_key',
    ];

    protected $fillable = [
        'organization_id',
        'calendly_connection_id',
        'url_token',
        'signing_key',
        'calendly_subscription_uri',
        'scope',
        'events',
        'status',
        'last_delivery_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'signing_key' => 'encrypted',
        'events' => 'array',
        'status' => CalendlyWebhookSubscriptionStatus::class,
        'last_delivery_at' => 'datetime',
    ];

    public function isActive(): bool {
        return $this->status === CalendlyWebhookSubscriptionStatus::Active;
    }

    /** @return BelongsTo<CalendlyConnection, $this> */
    public function connection(): BelongsTo {
        return $this->belongsTo(CalendlyConnection::class, 'calendly_connection_id');
    }
}
