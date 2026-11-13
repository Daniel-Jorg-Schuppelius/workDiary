<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationLoginPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Auth;

use App\Enums\Auth\LoginPolicyMode;
use App\Models\Concerns\{Auditable, BelongsToOrganization};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Anmelderichtlinie einer Organisation (Feature 205, MVP-1654–1657).
 *
 * Gelesen wird sie je Anfrage ({@see \App\Http\Middleware\EnforceLoginPolicy}),
 * deshalb liegt die Rohzeile kurz im Cache — nur als Array (Cache-Konvention)
 * und beim Speichern sofort verworfen, damit ein Notaus ohne Verzug wirkt.
 *
 * @property int $id
 * @property int $organization_id
 * @property LoginPolicyMode $hours_mode
 * @property array<array-key, mixed>|null $hours  ISO-Wochentag (1–7) => ['from' => 'HH:MM', 'until' => 'HH:MM']
 * @property list<string>|null $hours_roles  Rollen, für die die Zeiten gelten; leer = alle
 * @property LoginPolicyMode $network_mode
 * @property list<string>|null $networks  IPv4/IPv6, CIDR oder DNS-Name
 * @property list<string>|null $passkey_roles
 * @property Carbon|null $lockdown_at
 * @property int|null $lockdown_by
 * @property string|null $lockdown_reason
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrganizationLoginPolicy extends Model {
    use Auditable, BelongsToOrganization;

    private const string CACHE_PREFIX = 'login-policy:1:';

    private const int CACHE_SECONDS = 60;

    protected $fillable = [
        'organization_id',
        'hours_mode',
        'hours',
        'hours_roles',
        'network_mode',
        'networks',
        'passkey_roles',
        'lockdown_at',
        'lockdown_by',
        'lockdown_reason',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'hours_mode' => LoginPolicyMode::class,
        'hours' => 'array',
        'hours_roles' => 'array',
        'network_mode' => LoginPolicyMode::class,
        'networks' => 'array',
        'passkey_roles' => 'array',
        'lockdown_at' => 'datetime',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'hours_mode' => 'off',
        'network_mode' => 'off',
    ];

    protected static function booted(): void {
        static::saved(static fn (self $policy) => Cache::forget(self::cacheKey((int) $policy->organization_id)));
        static::deleted(static fn (self $policy) => Cache::forget(self::cacheKey((int) $policy->organization_id)));
    }

    public static function cacheKey(int $organizationId): string {
        return self::CACHE_PREFIX . $organizationId;
    }

    /**
     * Richtlinie einer Organisation ohne gebundenen Org-Kontext (Anmeldung,
     * Middleware, Konsole); null, wenn keine besteht.
     */
    public static function forOrganization(int $organizationId): ?self {
        $key = self::cacheKey($organizationId);
        $row = Cache::get($key);
        if (! is_array($row)) {
            $policy = self::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->first();
            $row = $policy instanceof self ? $policy->getAttributes() : [];
            Cache::put($key, $row, self::CACHE_SECONDS);
        }

        if ($row === [] || ! isset($row['organization_id'])) {
            return null;
        }

        return (new self)->newFromBuilder($row);
    }

    public function isLockedDown(): bool {
        return $this->lockdown_at !== null;
    }

    /** @return array<int, array{from: string, until: string}> */
    public function hoursByWeekday(): array {
        $result = [];
        foreach ((array) ($this->hours ?? []) as $day => $window) {
            if (is_array($window) && is_string($window['from'] ?? null) && is_string($window['until'] ?? null)) {
                $result[(int) $day] = ['from' => $window['from'], 'until' => $window['until']];
            }
        }

        return $result;
    }

    /** @return list<string> */
    public function hourRoleNames(): array {
        return self::stringList($this->hours_roles);
    }

    /** @return list<string> */
    public function networkEntries(): array {
        return self::stringList($this->networks);
    }

    /** @return list<string> */
    public function passkeyRoleNames(): array {
        return self::stringList($this->passkey_roles);
    }

    /** @return BelongsTo<User, $this> */
    public function lockdownActor(): BelongsTo {
        return $this->belongsTo(User::class, 'lockdown_by');
    }

    /**
     * Notaus auslösen bzw. aufheben bekommt einen eigenen Audit-Namen; der
     * Grund steht in den geänderten Feldern („Audit mit Grund“).
     *
     * @param  array<string, mixed>  $changes
     */
    protected function resolveAuditEvent(string $event, array $changes): string {
        if ($event === 'updated' && array_key_exists('lockdown_at', $changes)) {
            return $changes['lockdown_at'] === null ? 'login_policy.lockdown_lifted' : 'login_policy.lockdown_engaged';
        }

        return $event;
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array {
        return is_array($value)
            ? array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== ''))
            : [];
    }
}
