<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Notification;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\NotificationRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};

/**
 * Benachrichtigungsregel pro Organisation und Ereignistyp (MVP-018).
 *
 * Existiert für ein Ereignis keine Zeile, gilt der Code-Default aus
 * {@see NotificationEvent} (enabled, inApp+mail, Empfänger laut Event) —
 * dadurch braucht es kein Seeding pro Organisation und neue Ereignistypen
 * sind sofort mit sinnvollen Defaults aktiv.
 *
 * @property int $id
 * @property int $organization_id
 * @property NotificationEvent $event
 * @property bool $enabled
 * @property list<string> $channels
 * @property bool $notify_affected
 * @property list<string>|null $recipient_roles
 * @property list<int>|null $recipient_user_ids
 * @property bool $escalation_enabled
 * @property int|null $escalate_after_hours
 * @property string|null $escalation_role
 */
class NotificationRule extends Model {
    use Auditable;

    use BelongsToOrganization;

    /** @use HasFactory<NotificationRuleFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'event',
        'enabled',
        'channels',
        'notify_affected',
        'recipient_roles',
        'recipient_user_ids',
        'escalation_enabled',
        'escalate_after_hours',
        'escalation_role',
        'override_quiet_hours',
    ];

    protected $casts = [
        'event' => NotificationEvent::class,
        'enabled' => 'boolean',
        'override_quiet_hours' => 'boolean',
        'channels' => 'array',
        'notify_affected' => 'boolean',
        'recipient_roles' => 'array',
        'recipient_user_ids' => 'array',
        'escalation_enabled' => 'boolean',
        'escalate_after_hours' => 'integer',
    ];

    protected static function newFactory(): NotificationRuleFactory {
        return NotificationRuleFactory::new();
    }

    /**
     * Liefert die wirksame Regel einer Organisation für ein Ereignis:
     * die gespeicherte Zeile oder — wenn keine existiert — eine
     * (nicht persistierte) Default-Instanz aus dem Event.
     */
    public static function resolveFor(int $organizationId, NotificationEvent $event): self {
        $rule = static::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('event', $event->value)
            ->first();

        return $rule ?? static::defaultFor($organizationId, $event);
    }

    /** Default-Regel (nicht persistiert) gemäß Event-Registry. */
    public static function defaultFor(int $organizationId, NotificationEvent $event): self {
        $rule = new self([
            'event' => $event->value,
            'enabled' => true,
            'channels' => $event->defaultChannels(),
            'notify_affected' => $event->defaultNotifyAffected(),
            'recipient_roles' => $event->defaultRecipientRoles(),
            'recipient_user_ids' => [],
            'escalation_enabled' => false,
            'escalate_after_hours' => null,
            'escalation_role' => null,
        ]);
        $rule->organization_id = $organizationId;

        return $rule;
    }

    public function usesChannel(NotificationChannel $channel): bool {
        return in_array($channel->value, (array) $this->channels, true);
    }
}
