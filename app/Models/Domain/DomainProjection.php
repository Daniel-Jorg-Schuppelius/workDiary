<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainProjection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Domain;

use App\Enums\Domain\{DomainProviderCommandStatus, DomainRenewalMode, DomainSyncStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer\{Customer, ForeignCustomer};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Domain-Projektion (Feature 083, MVP-387). DomainReselling bleibt führend;
 * gespeichert werden Registrarstatus, Laufzeit/Ablauf, Renewal, Preis,
 * Transferlock, Revision + `raw_hash` sowie die eigene Kundenzuordnung. Der
 * Auth-Code wird NICHT dauerhaft in der Projektion gehalten.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $connection_id
 * @property string $external_domain
 * @property string $domain_hash
 * @property string $external_user
 * @property int|null $reseller_account_id
 * @property int|null $customer_id
 * @property int|null $foreign_customer_id
 * @property bool $is_own_holding
 * @property string|null $registrar
 * @property string|null $status
 * @property DomainSyncStatus $sync_status
 * @property DomainRenewalMode|null $renewal_mode
 * @property string|null $next_action
 * @property bool|null $transferlock
 * @property Carbon|null $registration_at
 * @property Carbon|null $expiration_at
 * @property Carbon|null $accounting_at
 * @property Carbon|null $failure_at
 * @property Carbon|null $paid_until_at
 * @property Carbon|null $next_action_at
 * @property Carbon|null $provider_updated_at
 * @property int|null $renewal_period
 * @property array<string, mixed>|null $details
 * @property Carbon|null $finalization_at
 * @property float|string|null $renewal_price
 * @property CurrencyCode|null $renewal_currency
 * @property string|null $revision
 * @property string|null $owner_handle
 * @property string|null $raw_hash
 * @property Carbon|null $synced_at
 */
class DomainProjection extends Model {
    /** Lesbare Form des Domainnamens: IDN als Unicode („onlinehändler-…“ statt „xn--…“); `external_domain` bleibt die Kennung des Anbieters. */
    public function displayName(): string {
        return \App\Support\DomainNames::display((string) $this->external_domain);
    }

    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'connection_id',
        'external_domain',
        'domain_hash',
        'external_user',
        'reseller_account_id',
        'owner_handle',
        'customer_id',
        'foreign_customer_id',
        'is_own_holding',
        'registrar',
        'status',
        'sync_status',
        'renewal_mode',
        'next_action',
        'transferlock',
        'registration_at',
        'expiration_at',
        'accounting_at',
        'failure_at',
        'finalization_at',
        'paid_until_at',
        'next_action_at',
        'provider_updated_at',
        'renewal_period',
        'details',
        'renewal_price',
        'renewal_currency',
        'revision',
        'raw_hash',
        'synced_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sync_status' => DomainSyncStatus::class,
        'renewal_mode' => DomainRenewalMode::class,
        'renewal_currency' => CurrencyCode::class,
        'transferlock' => 'boolean',
        'is_own_holding' => 'boolean',
        'registration_at' => 'date',
        'expiration_at' => 'date',
        'accounting_at' => 'date',
        'failure_at' => 'date',
        'finalization_at' => 'date',
        'paid_until_at' => 'date',
        'next_action_at' => 'date',
        'provider_updated_at' => 'datetime',
        'renewal_period' => 'integer',
        'details' => 'array',
        'renewal_price' => 'decimal:2',
        'synced_at' => 'datetime',
    ];

    /** Deterministischer Hash für Unique/Lookup (lower-case Domainname). */
    public static function hashFor(string $domain): string {
        return CryptoHelper::hash(mb_strtolower(trim($domain)));
    }

    /**
     * Abgleichsstand aus den offenen Befehlen je Domain (MVP-1620): Konflikt
     * vor unklarem Ausgang vor laufendem Befehl. Ein Abgleich überschreibt
     * ihn nicht, solange der Befehl offen ist.
     *
     * @return array<string, DomainSyncStatus> kleingeschriebener Domainname → Stand
     */
    public static function commandSyncStatuses(int $organizationId, ?string $domain = null): array {
        $weight = static fn (DomainSyncStatus $status): int => match ($status) {
            DomainSyncStatus::Conflict => 3,
            DomainSyncStatus::Unknown => 2,
            DomainSyncStatus::Pending => 1,
            default => 0,
        };
        $statuses = [];
        DomainProviderCommand::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('status', [DomainProviderCommandStatus::Pending->value, DomainProviderCommandStatus::Unknown->value, DomainProviderCommandStatus::Conflict->value])
            ->whereNotNull('target')
            ->when($domain !== null, fn ($query) => $query->where('target', trim((string) $domain)))
            ->get(['target', 'status'])
            ->each(function (DomainProviderCommand $command) use ($weight, &$statuses): void {
                $status = match ($command->status) {
                    DomainProviderCommandStatus::Pending => DomainSyncStatus::Pending,
                    DomainProviderCommandStatus::Unknown => DomainSyncStatus::Unknown,
                    DomainProviderCommandStatus::Conflict => DomainSyncStatus::Conflict,
                    default => null,
                };
                if ($status === null) {
                    return;
                }
                $key = mb_strtolower(trim((string) $command->target));
                if (! isset($statuses[$key]) || $weight($status) > $weight($statuses[$key])) {
                    $statuses[$key] = $status;
                }
            });

        return $statuses;
    }

    /**
     * Folgt dem Stand eines Befehls auf seiner Domain: offen → laufend,
     * unklar oder Konflikt; erledigt ohne weiteren offenen Befehl → veraltet,
     * bis der nächste Abgleich die Wirkung liest.
     */
    public static function followCommand(DomainProviderCommand $command): void {
        if ($command->organization_id === null || trim((string) $command->target) === '') {
            return;
        }
        $projection = self::query()->withoutGlobalScopes()
            ->where('organization_id', $command->organization_id)
            ->where('domain_hash', self::hashFor((string) $command->target))
            ->first();
        if ($projection === null) {
            return;
        }
        $open = self::commandSyncStatuses((int) $command->organization_id, (string) $command->target)[mb_strtolower(trim((string) $command->target))] ?? null;
        $target = $open ?? (in_array($projection->sync_status, [DomainSyncStatus::Pending, DomainSyncStatus::Unknown, DomainSyncStatus::Conflict], true) ? DomainSyncStatus::Stale : $projection->sync_status);
        if ($target !== $projection->sync_status) {
            $projection->forceFill(['sync_status' => $target->value])->save();
        }
    }

    /** @return BelongsTo<DomainProviderConnection, $this> */
    public function connection(): BelongsTo {
        return $this->belongsTo(DomainProviderConnection::class, 'connection_id');
    }

    /** Nicht-nullbare Verbindung (FK ist NOT NULL). */
    public function providerConnection(): DomainProviderConnection {
        $connection = $this->getRelationValue('connection');
        if (! $connection instanceof DomainProviderConnection) {
            throw new \RuntimeException('DomainProjection ohne Verbindung.');
        }

        return $connection;
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Endkunde des zugeordneten Kunden (Reseller-Fall).
     *
     * @return BelongsTo<ForeignCustomer, $this>
     */
    public function foreignCustomer(): BelongsTo {
        return $this->belongsTo(ForeignCustomer::class, 'foreign_customer_id');
    }

    /** @return BelongsTo<DomainResellerAccount, $this> */
    public function resellerAccount(): BelongsTo {
        return $this->belongsTo(DomainResellerAccount::class, 'reseller_account_id');
    }

    /** @return HasMany<DomainDnsZoneProjection, $this> */
    public function dnsZones(): HasMany {
        return $this->hasMany(DomainDnsZoneProjection::class, 'domain_projection_id');
    }
}
