<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChatWebhook.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Notification\NotificationChannel;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;

/**
 * Ausgehender Team-Messenger-Kanal einer Organisation (Feature 056, MVP-119):
 * Microsoft Teams oder Mattermost/Rocket.Chat Incoming Webhook. Die Kanal-URL
 * ist at-rest verschlüsselt (`encrypted`-Cast, APP_KEY) und nie serialisiert/
 * auditiert (`$hidden`). Auto-Deaktivierung nach wiederholten Fehlern
 * (`consecutive_failures`/`disabled_at`, analog {@see Integration\WebhookEndpoint}).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $kind
 * @property string $webhook_url
 * @property bool $active
 * @property int $consecutive_failures
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $last_delivery_at
 * @property \Illuminate\Support\Carbon|null $disabled_at
 */
class ChatWebhook extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    public const KIND_TEAMS = 'teams';

    public const KIND_MATTERMOST = 'mattermost';

    /** Auto-Deaktivierung nach so vielen aufeinanderfolgenden Fehlversuchen (0 = nie). */
    public const AUTO_DISABLE_THRESHOLD = 10;

    protected $table = 'chat_webhooks';

    /** Geheimnis nie serialisieren/auditieren. */
    protected $hidden = [
        'webhook_url',
    ];

    protected $fillable = [
        'organization_id',
        'name',
        'kind',
        'webhook_url',
        'active',
        'last_delivery_at',
        'consecutive_failures',
        'disabled_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'webhook_url' => 'encrypted',
        'active' => 'boolean',
        'last_delivery_at' => 'datetime',
        'consecutive_failures' => 'integer',
        'disabled_at' => 'datetime',
    ];

    public function isActive(): bool {
        return $this->active && $this->disabled_at === null;
    }

    /** Der Benachrichtigungskanal, über den die Ereignis-Matrix diesen Webhook auswählt. */
    public function channel(): NotificationChannel {
        return $this->kind === self::KIND_TEAMS ? NotificationChannel::Teams : NotificationChannel::Mattermost;
    }
}
