<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainResellerAccount.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Domain;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Customer;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Subuser-/Subreseller-Projektion (Feature 083, MVP-386). Herkunft, Parent
 * und Hierarchietiefe bleiben erhalten (keine Glättung). Optionale
 * WorkDiary-Kundenzuordnung gruppiert die Domains in der Kundenakte, ohne
 * beim Provider etwas zu verschieben.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $connection_id
 * @property string $external_user
 * @property string|null $parent_user
 * @property int $depth
 * @property string|null $user_class
 * @property bool $active
 * @property CurrencyCode|null $currency
 * @property float|string|null $balance_snapshot
 * @property Carbon|null $balance_at
 * @property int|null $customer_id
 * @property string|null $raw_hash
 * @property Carbon|null $synced_at
 */
class DomainResellerAccount extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'connection_id',
        'external_user',
        'parent_user',
        'depth',
        'user_class',
        'active',
        'currency',
        'balance_snapshot',
        'balance_at',
        'customer_id',
        'raw_hash',
        'synced_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'depth' => 'integer',
        'active' => 'boolean',
        'currency' => CurrencyCode::class,
        'balance_snapshot' => 'decimal:2',
        'balance_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    /** @return BelongsTo<DomainProviderConnection, $this> */
    public function connection(): BelongsTo {
        return $this->belongsTo(DomainProviderConnection::class, 'connection_id');
    }

    /** Nicht-nullbare Verbindung (FK ist NOT NULL). */
    public function providerConnection(): DomainProviderConnection {
        $connection = $this->getRelationValue('connection');
        if (! $connection instanceof DomainProviderConnection) {
            throw new \RuntimeException('DomainResellerAccount ohne Verbindung.');
        }

        return $connection;
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** @return HasMany<DomainProjection, $this> */
    public function domains(): HasMany {
        return $this->hasMany(DomainProjection::class, 'reseller_account_id');
    }
}
