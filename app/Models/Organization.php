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

use App\Models\Concerns\Auditable;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model {
    use Auditable;

    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

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
        'owner_id',
        'trial_ends_at',
    ];

    protected function casts(): array {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'trial_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void {
        static::creating(function (Organization $org): void {
            if (! $org->slug) {
                $base = Str::slug($org->name) ?: 'org';
                $slug = $base;
                $i = 2;
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
        $stored = (array) ($this->settings['compliance'] ?? []);
        $merged = array_replace_recursive(self::COMPLIANCE_DEFAULTS, $stored);

        /** @var array{mode:string, max_hours_day:int, min_rest_hours:int, max_hours_week:int, max_consecutive_days:int, rules:array<string,bool>} $merged */
        return $merged;
    }
}
