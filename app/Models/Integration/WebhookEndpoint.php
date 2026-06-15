<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookEndpoint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Integration;

use App\Enums\Integration\WebhookEvent;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\{Carbon, Str};

/**
 * Ausgehender Webhook-Endpunkt einer Organisation (Feature 008).
 *
 * `secret` ist der HMAC-SHA256-Signing-Key und wird verschlüsselt at-rest
 * abgelegt (encrypted-Cast → APP_KEY). Er wird NIE serialisiert ($hidden)
 * und nur EINMAL bei der Erstellung/Rotation im Klartext angezeigt; die
 * Signaturprüfung beim Empfänger erfolgt mit derselben Geheimnis-Kopie.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $label
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property bool $active
 * @property int|null $created_by_user_id
 * @property Carbon|null $last_delivery_at
 * @property int $consecutive_failures
 * @property Carbon|null $disabled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WebhookEndpoint extends Model {
    use Auditable, BelongsToOrganization, HasSqid, SoftDeletes;
    /** @use HasFactory<\Database\Factories\Integration\WebhookEndpointFactory> */
    use HasFactory;

    /** Aufeinanderfolgende Fehlversuche, ab denen der Endpunkt auto-deaktiviert wird. */
    public const MAX_CONSECUTIVE_FAILURES = 10;

    protected $fillable = [
        'organization_id',
        'label',
        'url',
        'secret',
        'events',
        'active',
        'created_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'secret' => 'encrypted',
        'events' => 'array',
        'active' => 'boolean',
        'last_delivery_at' => 'datetime',
        'consecutive_failures' => 'integer',
        'disabled_at' => 'datetime',
    ];

    /**
     * Verhindert das Auslesen des Signing-Keys über JSON/Array-Serialisierung
     * (Responses, Logs, ->toArray()).
     *
     * @var list<string>
     */
    protected $hidden = ['secret'];

    /** Erzeugt einen kryptografisch sicheren HMAC-Signing-Key (Hex, 64 Zeichen). */
    public static function generateSecret(): string {
        return bin2hex(random_bytes(32));
    }

    /** Ist der Endpunkt für den Versand bereit (aktiv und nicht auto-deaktiviert)? */
    public function isDeliverable(): bool {
        return $this->active && $this->disabled_at === null;
    }

    /** Hört dieser Endpunkt auf das gegebene Webhook-Ereignis? */
    public function subscribesTo(WebhookEvent $event): bool {
        return in_array($event->value, (array) $this->events, true);
    }

    /** Maskierte Vorschau (z. B. „whk_…3f1a") für die UI, nie der Klartext-Key. */
    public function secretPreview(): string {
        $secret = (string) $this->secret;

        return $secret === '' ? '' : 'whk_…' . Str::substr($secret, -4);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany {
        return $this->hasMany(WebhookDelivery::class);
    }
}
