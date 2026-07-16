<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiProviderConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Ai;

use App\Enums\Ai\{AiConnectionStatus, AiFamily, AiProviderType, AiVerb};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * KI-Provider-Verbindung (Feature 025, MVP-399): eine Organisation kann
 * MEHRERE Verbindungen je Familie führen (auch mehrere desselben Typs).
 * Der API-Schlüssel ist verschlüsselt und `hidden` — er erscheint nie in
 * Serialisierung, Audit oder Logs. `is_local` entscheidet zusammen mit
 * Capability-Sensibilität und Branchenprofil, ob die Verbindung geroutet
 * werden darf; bei Typen ohne freie Einstufung erzwingt das Model den
 * Typ-Default (nur `openai_compatible` darf der Admin bewusst einstufen).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property AiFamily $family
 * @property AiProviderType $provider
 * @property string $name
 * @property string|null $base_url
 * @property string|null $api_key
 * @property string|null $model
 * @property array<string, mixed>|null $options
 * @property bool $is_local
 * @property AiConnectionStatus $status
 * @property Carbon|null $preflight_at
 * @property string|null $last_error
 * @property Carbon|null $last_error_at
 * @property int $consecutive_failures
 * @property Carbon|null $disabled_at
 */
class AiProviderConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'family',
        'provider',
        'name',
        'base_url',
        'api_key',
        'model',
        'options',
        'is_local',
        'status',
        'preflight_at',
        'created_by_user_id',
    ];

    /** API-Schlüssel nie in Arrays/JSON/Audit/Logs. */
    protected $hidden = [
        'api_key',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'family' => AiFamily::class,
        'provider' => AiProviderType::class,
        'status' => AiConnectionStatus::class,
        'api_key' => 'encrypted',
        'options' => 'array',
        'is_local' => 'boolean',
        'preflight_at' => 'datetime',
        'last_error_at' => 'datetime',
        'disabled_at' => 'datetime',
    ];

    protected static function booted(): void {
        static::saving(function (self $connection): void {
            // Lokalität ist nur beim generischen Adapter eine bewusste
            // Admin-Entscheidung; alle anderen Typen erzwingen den Default
            // (Sensibilitäts-Gate darf nicht per Datenpflege kippen).
            if (! $connection->provider->allowsLocalOverride()) {
                $connection->is_local = $connection->provider->isLocalByDefault();
            }
        });
    }

    /** Betriebsbereit nur mit aktivem Status und ohne Auto-Disable. */
    public function isRunnable(): bool {
        return $this->status->isRunnable() && ! $this->isConnectionFailing();
    }

    public function isCloud(): bool {
        return ! $this->is_local;
    }

    /** Bedient die Verbindung das Verb (Familien-Zuordnung)? */
    public function supportsVerb(AiVerb $verb): bool {
        return in_array($this->family, $verb->allowedFamilies(), true);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
