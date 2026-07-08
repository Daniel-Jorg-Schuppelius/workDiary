<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Organization.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Organization\TenantStatus;
use App\Models\Concerns\{Auditable, HasAttachments, HasSqid};
use App\Services\Licensing\{LicenseResult, LicenseService};
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\{Carbon, Str};

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $plan
 * @property string|null $locale
 * @property string|null $timezone
 * @property array<string, mixed>|null $settings
 * @property bool $is_active
 * @property TenantStatus|null $tenant_status
 * @property int|null $owner_id
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Organization extends Model {
    use Auditable;
    use HasAttachments;

    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    use HasSqid;

    public const PLAN_FREE = 'free';

    public const PLAN_PRO = 'pro';

    public const PLAN_ENTERPRISE = 'enterprise';

    public const COMPLIANCE_OFF = 'off';

    public const COMPLIANCE_WARN = 'warn';

    public const COMPLIANCE_BLOCK = 'block';

    /** @var list<string> */
    public static array $complianceModes = [
        self::COMPLIANCE_OFF,
        self::COMPLIANCE_WARN,
        self::COMPLIANCE_BLOCK,
    ];

    /** Default-Compliance-Settings (ArbZG-Standard). */
    public const COMPLIANCE_DEFAULTS = [
        'mode' => self::COMPLIANCE_WARN,
        'max_hours_day' => 10,
        'min_rest_hours' => 11,
        'max_hours_week' => 48,
        'max_consecutive_days' => 6,
        'rules' => [
            'overlap' => true,
            'rest_period' => true,
            'max_daily_hours' => true,
            'max_weekly_hours' => true,
            'consecutive_days' => true,
            'vacation_conflict' => true,
            'qualification_match' => true,
            'holiday_double_book' => true,
        ],
    ];

    /** @var list<string> */
    public static array $plans = [
        self::PLAN_FREE,
        self::PLAN_PRO,
        self::PLAN_ENTERPRISE,
    ];

    public static function planLabel(?string $plan): string {
        if ($plan === null || $plan === '') {
            return '';
        }

        return (string) __("values.{$plan}");
    }

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'license_key',
        'license_uid',
        'locale',
        'timezone',
        'settings',
        'is_active',
        'tenant_status',
        'deactivated_at',
        'owner_id',
        'trial_ends_at',
        'is_demo',
        'demo_seeded_at',
        'two_factor_required',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'tenant_status' => TenantStatus::class,
        'two_factor_required' => 'boolean',
        'trial_ends_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'is_demo' => 'boolean',
        'demo_seeded_at' => 'datetime',
    ];

    protected static function booted(): void {
        static::creating(function (Organization $org): void {
            // Stabile Bindungs-ID fuer org-gebundene Lizenzen.
            if (! $org->license_uid) {
                $org->license_uid = (string) Str::uuid();
            }
            if (! $org->slug) {
                $base = Str::slug($org->name) ?: 'org';
                $slug = $base;
                $i = 2;
                // TENANT-BYPASS: Organization ist Root-Tenant und selbst nicht
                // mandantenscoped; Slug-Eindeutigkeit ist global zu prüfen.
                while (static::withoutGlobalScopes()->where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $org->slug = $slug;
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Lohn-/Sozialversicherungs-Stammdaten aus settings['payroll'].
     * Ohne Schlüssel die gesamte Gruppe; sonst der Einzelwert (oder null).
     *
     * @return ($key is null ? array<string, mixed> : string|null)
     */
    public function payroll(?string $key = null): array|string|null {
        $payroll = is_array($this->settings['payroll'] ?? null) ? $this->settings['payroll'] : [];
        if ($key === null) {
            return $payroll;
        }

        $value = $payroll[$key] ?? null;

        return ($value === null || $value === '') ? null : (string) $value;
    }

    /**
     * Organisationsweiter Standard-Arbeitszeit-Typ (Vorbelegung neuer
     * Arbeitszeit-Modelle). Override in settings['timesheet']['default_schedule_type'],
     * Fallback ist der config-Default.
     */
    public function defaultScheduleType(): string {
        $stored = $this->settings['timesheet']['default_schedule_type'] ?? null;

        return is_string($stored) && $stored !== ''
            ? $stored
            : (string) config('timesheet.defaults.schedule_type', 'flextime');
    }

    /**
     * Freigabemodus für Verkaufspreisübernahmen (Feature 050, MVP-095):
     * `direct` übernimmt sofort (Standard), `four_eyes` verlangt Antrag und
     * Genehmigung durch eine zweite Person. Ablage in
     * settings['pricing']['approval_mode'].
     */
    public function pricingApprovalMode(): string {
        $stored = $this->settings['pricing']['approval_mode'] ?? null;

        return $stored === 'four_eyes' ? 'four_eyes' : 'direct';
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany {
        return $this->hasMany(User::class);
    }

    /**
     * Aktive Nutzer dieser Organisation – Bezugsgröße für das Lizenz-Nutzerlimit
     * (Feature 021). User nutzen keinen BelongsToOrganization-GlobalScope, daher
     * explizit ohne Scopes gegen `organization_id` zählen.
     */
    public function activeUserCount(): int {
        return User::withoutGlobalScopes()
            ->where('organization_id', $this->getKey())
            ->count();
    }

    /**
     * Effektiver SaaS-Mandantenstatus (Feature 021). Ein explizit gesetzter
     * `tenant_status` hat Vorrang; sonst wird abgeleitet:
     *  - `suspended`, wenn die Org deaktiviert ist (`is_active = false`),
     *  - `expired`, wenn die org-gebundene Lizenz endgültig abgelaufen ist,
     *  - `trial`, solange `trial_ends_at` in der Zukunft liegt,
     *  - sonst `active`.
     *
     * Der optionale Lizenz-Status wird hereingereicht, um Doppel-Auflösung zu
     * vermeiden; fehlt er, wird er bei Bedarf über {@see LicenseService} geholt.
     */
    public function tenantStatus(?LicenseResult $license = null): TenantStatus {
        if ($this->tenant_status instanceof TenantStatus) {
            return $this->tenant_status;
        }

        if (! $this->is_active) {
            return TenantStatus::Suspended;
        }

        $license ??= ($this->license_key ? app(LicenseService::class)->forOrganization($this) : null);
        if ($license !== null && $license->status === \App\Services\Licensing\LicenseStatus::Expired) {
            return TenantStatus::Expired;
        }

        if ($this->trial_ends_at !== null && $this->trial_ends_at->isFuture()) {
            return TenantStatus::Trial;
        }

        return TenantStatus::Active;
    }

    /** Sperrt der aktuelle Mandantenstatus schreibende Aktionen? */
    public function tenantWritesBlocked(?LicenseResult $license = null): bool {
        return $this->tenantStatus($license)->blocksWrites();
    }

    /**
     * Wartungsmodus-Einstellungen (Rang 65) aus `settings.maintenance`.
     * `until` wird — falls gesetzt — als Carbon geparst; ungültige Werte
     * fallen auf null zurück (Wartung dann ohne Endzeitpunkt).
     *
     * @return array{enabled:bool, message:?string, until:?\Carbon\CarbonInterface, block_ingest:bool}
     */
    public function maintenanceSettings(): array {
        $settings = (array) ($this->settings ?? []);
        $stored = is_array($settings['maintenance'] ?? null) ? $settings['maintenance'] : [];

        $until = null;
        if (! empty($stored['until']) && is_string($stored['until'])) {
            try {
                $until = \Carbon\CarbonImmutable::parse($stored['until'], config('app.timezone'));
            } catch (\Throwable) {
                $until = null;
            }
        }

        return [
            'enabled' => (string) ($stored['enabled'] ?? '0') === '1',
            'message' => isset($stored['message']) && is_string($stored['message']) && $stored['message'] !== '' ? $stored['message'] : null,
            'until' => $until,
            'block_ingest' => (string) ($stored['block_ingest'] ?? '0') === '1',
        ];
    }

    /** Ist der Mandant aktuell im Wartungsmodus (Endzeitpunkt berücksichtigt)? */
    public function inMaintenance(): bool {
        $settings = $this->maintenanceSettings();
        if (! $settings['enabled']) {
            return false;
        }

        return $settings['until'] === null || $settings['until']->isFuture();
    }

    /** Sollen während der Wartung auch Terminal-/Webhook-Ingests pausieren? */
    public function maintenanceBlocksIngest(): bool {
        return $this->inMaintenance() && $this->maintenanceSettings()['block_ingest'];
    }

    /**
     * Compliance-Settings inkl. Defaults (rekursiv gemerged).
     *
     * @return array{mode:string, max_hours_day:int, min_rest_hours:int, max_hours_week:int, max_consecutive_days:int, rules:array<string,bool>}
     */
    public function complianceSettings(): array {
        $settings = $this->settings ?? [];
        $stored = is_array($settings['compliance'] ?? null) ? $settings['compliance'] : [];
        $merged = array_replace_recursive(self::COMPLIANCE_DEFAULTS, $stored);

        /** @var array{mode:string, max_hours_day:int, min_rest_hours:int, max_hours_week:int, max_consecutive_days:int, rules:array<string,bool>} $merged */
        return $merged;
    }

    /**
     * Generic merger: returns the organisation's overrides for the given
     * settings group merged on top of the matching `config/<group>.php`
     * defaults. Returns the raw config when no overrides are stored.
     *
     * @return array<string, mixed>
     */
    public function groupSettings(string $group): array {
        /** @var array<string, mixed> $defaults */
        $defaults = (array) config($group, []);
        /** @var array<string, mixed> $settings */
        $settings = (array) ($this->settings ?? []);
        /** @var array<string, mixed> $stored */
        $stored = (array) ($settings[$group] ?? []);

        /** @var array<string, mixed> $merged */
        $merged = array_replace_recursive($defaults, $stored);

        return $merged;
    }

    /** @return array<string, mixed> */
    public function paginationSettings(): array {
        return $this->groupSettings('pagination');
    }

    /** @return array<string, mixed> */
    public function invoicingSettings(): array {
        return $this->groupSettings('invoicing');
    }

    /** @return array<string, mixed> */
    public function uploadSettings(): array {
        return $this->groupSettings('uploads');
    }

    /** @return array<string, mixed> */
    public function validationSettings(): array {
        return $this->groupSettings('validation');
    }

    /** @return array<string, mixed> */
    public function notificationSettings(): array {
        return $this->groupSettings('notifications');
    }

    /** @return array<string, mixed> */
    public function uiSettings(): array {
        return $this->groupSettings('ui');
    }

    /** @return array<string, mixed> */
    public function brandingSettings(): array {
        return $this->groupSettings('branding');
    }

    /**
     * Theming-Einstellungen (config/theme.php gemerged mit settings['theme']).
     * Enthält `builtin`/`auto`/`geometry` aus der Config sowie — falls gesetzt —
     * `custom` (Liste der Org-Themes) und `default` (Org-Default-Theme-Token).
     *
     * @return array<string, mixed>
     */
    public function themeSettings(): array {
        return $this->groupSettings('theme');
    }

    public function logo(): ?Attachment {
        return $this->attachmentByMeta(Attachment::META_LOGO);
    }

    public function logoDark(): ?Attachment {
        return $this->attachmentByMeta(Attachment::META_LOGO_DARK);
    }
}
