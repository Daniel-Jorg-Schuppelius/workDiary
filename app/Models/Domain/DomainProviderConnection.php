<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainProviderConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Domain;

use App\Enums\Domain\{DomainConnectionStatus, DomainProviderEnvironment};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * DomainReselling-Verbindung (Feature 083, MVP-385): per Organisation ein
 * Konto mit Umgebung, festem Endpunkt, Login und VERSCHLÜSSELTEM Passwort.
 * Passwort ist `hidden` und wird nie serialisiert/geloggt. `capabilities`
 * hält die erkannte Fähigkeitsmatrix; `pilot_confirmed_at` bleibt NULL,
 * solange kein realer Pilot bestanden ist.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property DomainProviderEnvironment $environment
 * @property string $name
 * @property string $endpoint
 * @property string $login
 * @property string|null $password
 * @property string|null $default_user
 * @property array<string, bool>|null $capabilities
 * @property DomainConnectionStatus $status
 * @property Carbon|null $pilot_confirmed_at
 * @property Carbon|null $last_sync_at
 * @property string|null $last_error
 * @property Carbon|null $last_error_at
 * @property int $consecutive_failures
 * @property Carbon|null $disabled_at
 */
class DomainProviderConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'environment',
        'name',
        'endpoint',
        'login',
        'password',
        'default_user',
        'capabilities',
        'status',
        'pilot_confirmed_at',
        'last_sync_at',
        'created_by_user_id',
    ];

    /** Passwort nie in Arrays/JSON/Logs. */
    protected $hidden = [
        'password',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'environment' => DomainProviderEnvironment::class,
        'status' => DomainConnectionStatus::class,
        'password' => 'encrypted',
        'capabilities' => 'array',
        'pilot_confirmed_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'last_error_at' => 'datetime',
        'disabled_at' => 'datetime',
    ];

    /** Betriebsbereit nur mit aktivem Status und ohne Auto-Disable. */
    public function isRunnable(): bool {
        return $this->status->isRunnable() && ! $this->isConnectionFailing();
    }

    /** Solange kein realer Pilot bestanden ist, bleibt der Adapter „Pilot offen". */
    public function pilotConfirmed(): bool {
        return $this->pilot_confirmed_at !== null;
    }

    /** @return HasMany<DomainProjection, $this> */
    public function projections(): HasMany {
        return $this->hasMany(DomainProjection::class, 'connection_id');
    }

    /** @return HasMany<DomainResellerAccount, $this> */
    public function resellerAccounts(): HasMany {
        return $this->hasMany(DomainResellerAccount::class, 'connection_id');
    }

    /** @return HasMany<DomainProviderCommand, $this> */
    public function commands(): HasMany {
        return $this->hasMany(DomainProviderCommand::class, 'connection_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
