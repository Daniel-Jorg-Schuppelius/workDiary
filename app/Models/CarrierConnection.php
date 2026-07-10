<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CarrierConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;

/**
 * Carrier-Anbindung einer Organisation (Feature 059, MVP-128). Die Zugangsdaten
 * sind at-rest verschlüsselt (`encrypted:array`-Cast, APP_KEY) und nie
 * serialisiert/auditiert (`$hidden`). `carrier` bindet an den passenden
 * {@see \App\Plugins\Contracts\ShippingProvider}.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $carrier
 * @property string $name
 * @property array<string, mixed> $credentials
 * @property string|null $billing_number
 * @property bool $sandbox
 * @property bool $active
 * @property int|null $created_by
 */
class CarrierConnection extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasConnectionHealth;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $table = 'carrier_connections';

    /** Zugangsdaten nie serialisieren/auditieren. */
    protected $hidden = [
        'credentials',
    ];

    protected $fillable = [
        'organization_id',
        'carrier',
        'name',
        'credentials',
        'billing_number',
        'sandbox',
        'active',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'credentials' => 'encrypted:array',
        'sandbox' => 'boolean',
        'active' => 'boolean',
    ];

    public function isActive(): bool {
        return $this->active;
    }

    /** Einzelnes Zugangsdatum (z. B. `api_key`, `username`), sonst null. */
    public function credential(string $key): ?string {
        $value = ($this->credentials ?? [])[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
