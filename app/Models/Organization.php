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

use App\Models\Concerns\{Auditable, HasAttachments, HasSqid};
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

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'locale',
        'timezone',
        'settings',
        'is_active',
        'deactivated_at',
        'owner_id',
        'trial_ends_at',
        'is_demo',
        'demo_seeded_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'is_demo' => 'boolean',
        'demo_seeded_at' => 'datetime',
    ];

    protected static function booted(): void {
        static::creating(function (Organization $org): void {
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

    /** @return HasMany<User, $this> */
    public function users(): HasMany {
        return $this->hasMany(User::class);
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

    public function logo(): ?Attachment {
        return $this->attachmentByMeta(Attachment::META_LOGO);
    }

    public function logoDark(): ?Attachment {
        return $this->attachmentByMeta(Attachment::META_LOGO_DARK);
    }
}
