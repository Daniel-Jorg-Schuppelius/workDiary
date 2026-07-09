<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth, HasSqid};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Support\Str;

/**
 * Telefonie-/CTI-Anbindung einer Organisation (Feature 056, MVP-118). Der
 * eingehende Webhook wird über einen Token im Pfad autorisiert; persistiert wird
 * nur der SHA-256-Hash (Klartext einmalig bei Ausstellung). Es werden nur
 * Anruf-Metadaten verarbeitet, nie Gesprächsinhalte.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $provider
 * @property string $webhook_token_hash
 * @property bool $active
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $last_event_at
 */
class CtiConnection extends Model {
    use HasConnectionHealth;

    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $table = 'cti_connections';

    /** @var array<string, string> */
    protected $casts = [
        'active' => 'boolean',
        'last_event_at' => 'datetime',
    ];

    protected $fillable = [
        'organization_id',
        'name',
        'provider',
        'webhook_token_hash',
        'active',
        'last_event_at',
        'created_by',
    ];

    public static function hashToken(string $plain): string {
        return CryptoHelper::hash($plain);
    }

    /**
     * Stellt eine Anbindung mit frischem Webhook-Token aus und gibt
     * [Model, Klartext] zurück; der Klartext ist danach nicht rekonstruierbar.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(int $organizationId, string $name, string $provider, ?int $createdBy = null): array {
        $plain = 'cti_' . Str::random(48);

        $connection = static::query()->create([
            'organization_id' => $organizationId,
            'name' => $name,
            'provider' => $provider,
            'webhook_token_hash' => static::hashToken($plain),
            'created_by' => $createdBy,
        ]);

        return [$connection, $plain];
    }

    public function isActive(): bool {
        return $this->active;
    }

    /** @param Builder<CtiConnection> $query */
    public function scopeActive(Builder $query): void {
        $query->where('active', true);
    }
}
