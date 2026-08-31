<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth};
use App\Models\Concerns\HasPrivateNetworkOptIn;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CardDAV-Leseanbindung einer Organisation (Bauturbo A9, MVP-329). Das
 * App-Passwort ist at-rest verschlüsselt (`encrypted`-Cast, APP_KEY) und nie
 * serialisiert/auditiert (`$hidden`). WorkDiary liest Kontakte aus dem per
 * RFC-6764-Discovery gewählten Adressbuch (`addressbook_url`) und speist sie
 * als Zuordnungsvorschläge in die Integrations-Inbox ein — rein lesend,
 * niemals schreibend Richtung Server.
 *
 * `allow_private_network` ist das auditierte SSRF-Opt-in für Server im
 * eigenen Netz (Muster JTL-Wawi); `sync_token` trägt den RFC-6578-Stand.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $base_url
 * @property string $username
 * @property string $app_password
 * @property string|null $addressbook_url
 * @property string|null $addressbook_name
 * @property string|null $sync_token
 * @property bool $allow_private_network
 * @property bool $active
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $last_synced_at
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $last_error_at
 * @property int $consecutive_failures
 * @property \Illuminate\Support\Carbon|null $disabled_at
 */
class CardDavConnection extends Model {
    use Auditable;

    use BelongsToOrganization;

    use HasConnectionHealth;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasPrivateNetworkOptIn;

    /** Tabellenname explizit (Klassenname würde sonst zu `card_dav_connections`). */
    protected $table = 'carddav_connections';

    /** Geheimnisse nie serialisieren/auditieren. */
    protected $hidden = [
        'app_password',
    ];

    protected $fillable = [
        'organization_id',
        'name',
        'base_url',
        'username',
        'app_password',
        'addressbook_url',
        'addressbook_name',
        'sync_token',
        'allow_private_network',
        'active',
        'last_synced_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'app_password' => 'encrypted',
        'allow_private_network' => 'boolean',
        'active' => 'boolean',
        'last_synced_at' => 'datetime',
        'last_error_at' => 'datetime',
        'disabled_at' => 'datetime',
    ];

    /** Betriebsbereit: aktiv geschaltet und vollständig konfiguriert. */
    public function isActive(): bool {
        return $this->active && $this->base_url !== '' && $this->username !== '' && $this->app_password !== '';
    }

    /**
     * Sync-fähig: betriebsbereit, Adressbuch gewählt und nicht per
     * Auto-Disable (HasConnectionHealth) stillgelegt.
     */
    public function isSyncable(): bool {
        return $this->isActive()
            && $this->addressbook_url !== null && $this->addressbook_url !== ''
            && $this->disabled_at === null;
    }

    /** @return HasMany<CardDavCard, $this> */
    public function cards(): HasMany {
        return $this->hasMany(CardDavCard::class, 'carddav_connection_id');
    }
}
