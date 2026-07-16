<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudDocumentConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\CloudIntake;

use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeProvider};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Cloud-Dokumenteingang-Verbindung (Feature 080, MVP-352): OAuth-gebundenes
 * Provider-Konto + gewählter Container/Stammordner + persistierter
 * Checkpoint. Tokens liegen verschlüsselt und sind in Serialisierungen
 * verborgen; Fehlertexte werden redigiert gespeichert (max. 300 Zeichen,
 * {@see HasConnectionHealth}).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property CloudIntakeProvider $provider
 * @property string $name
 * @property string|null $external_account_id
 * @property string|null $external_account_label
 * @property string|null $server_url
 * @property string|null $username
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property array<int, string>|null $granted_scopes
 * @property string|null $container_id
 * @property string|null $container_label
 * @property string|null $root_folder_id
 * @property string|null $root_folder_path
 * @property CloudIntakeConnectionStatus $status
 * @property string|null $checkpoint
 * @property Carbon|null $last_run_at
 * @property string|null $subscription_id
 * @property Carbon|null $subscription_expires_at
 * @property string|null $webhook_secret
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CloudDocumentRoute> $routes
 */
class CloudDocumentConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'provider',
        'name',
        'external_account_id',
        'external_account_label',
        // Nextcloud (MVP-382): Server-URL + Nutzer; App-Passwort in access_token.
        'server_url',
        'username',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'granted_scopes',
        'container_id',
        'container_label',
        'root_folder_id',
        'root_folder_path',
        'status',
        'checkpoint',
        'last_run_at',
        'subscription_id',
        'subscription_expires_at',
        'webhook_secret',
        'created_by_user_id',
    ];

    /** Tokens/Webhook-Secret nie in Arrays/JSON/Logs. */
    protected $hidden = [
        'access_token',
        'refresh_token',
        'webhook_secret',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'provider' => CloudIntakeProvider::class,
        'status' => CloudIntakeConnectionStatus::class,
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'token_expires_at' => 'datetime',
        'granted_scopes' => 'array',
        'last_run_at' => 'datetime',
        'subscription_expires_at' => 'datetime',
    ];

    /** @return HasMany<CloudDocumentRoute, $this> */
    public function routes(): HasMany {
        return $this->hasMany(CloudDocumentRoute::class, 'connection_id');
    }

    /** @return HasMany<CloudDocumentItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(CloudDocumentItem::class, 'connection_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Nur aktiv UND mit mindestens einer aktiven Route läuft der Import. */
    public function isRunnable(): bool {
        return $this->status->isRunnable()
            && $this->routes()->where('active', true)->exists();
    }
}
