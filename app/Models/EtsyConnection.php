<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Etsy-OAuth-Verbindung einer Organisation (Feature 101, MVP-494): genau EINE
 * Verbindung je Org UND je Shop (ein Etsy-Shop kann nur an eine Organisation
 * gebunden sein). Tokens at-rest verschlüsselt (`encrypted`-Cast, APP_KEY),
 * nie serialisiert/auditiert (`$hidden`). Etsys Refresh-Token rotiert bei
 * jedem Refresh und läuft nach 90 Tagen Inaktivität ab —
 * `refresh_issued_at` trägt die Reconnect-Warnung im Healthcheck.
 * `webhook_token` ist der opake URL-Bestandteil des Ingest-Endpunkts
 * (Org-Auflösung NIE aus dem Payload). Auto-Disable über
 * {@see HasConnectionHealth}.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $shop_id
 * @property string|null $shop_name
 * @property int|null $etsy_user_id
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $refresh_issued_at
 * @property string|null $scopes
 * @property string $status
 * @property string|null $webhook_token
 * @property array<string, int|string>|null $checkpoints
 * @property Carbon|null $last_synced_at
 * @property array<string, int|bool>|null $last_sync_counters
 * @property int|null $connected_by
 */
class EtsyConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $table = 'etsy_connections';

    /** Geheimnisse nie serialisieren/auditieren. */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $fillable = [
        'organization_id',
        'shop_id',
        'shop_name',
        'etsy_user_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'refresh_issued_at',
        'scopes',
        'status',
        'webhook_token',
        'checkpoints',
        'last_synced_at',
        'last_sync_counters',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'shop_id' => 'integer',
        'etsy_user_id' => 'integer',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'refresh_issued_at' => 'datetime',
        'checkpoints' => 'array',
        'last_synced_at' => 'datetime',
        'last_sync_counters' => 'array',
        'last_error_at' => 'datetime',
        'disabled_at' => 'datetime',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    /**
     * Etsy ROTIERT das Refresh-Token bei jedem Refresh (90-Tage-Frist ab
     * Ausgabe) — jede Änderung stempelt `refresh_issued_at` neu, damit der
     * Healthcheck die Reconnect-Warnung am tatsächlichen Token-Alter misst.
     */
    protected static function booted(): void {
        static::saving(function (self $connection): void {
            if ($connection->isDirty('refresh_token') && trim((string) $connection->refresh_token) !== '') {
                $connection->refresh_issued_at = Carbon::now();
            }
        });
    }

    /** Betriebsbereit: verbunden, Token + Shop vorhanden, nicht auto-deaktiviert. */
    public function isActive(): bool {
        return $this->status === self::STATUS_ACTIVE
            && trim((string) $this->access_token) !== ''
            && $this->shop_id !== null
            && $this->disabled_at === null;
    }

    /** Einzelnen Aufholpunkt lesen (Epoch-Sekunden), 0 = nicht gesetzt. */
    public function checkpoint(string $key): int {
        $value = $this->checkpoints[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /** Einzelnen Aufholpunkt fortschreiben (persistiert sofort). */
    public function rememberCheckpoint(string $key, int $epoch): void {
        $checkpoints = (array) $this->checkpoints;
        $checkpoints[$key] = $epoch;
        $this->forceFill(['checkpoints' => $checkpoints])->save();
    }
}
