<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model {
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;
    use Auditable;

    public const PLAN_FREE       = 'free';
    public const PLAN_PRO        = 'pro';
    public const PLAN_ENTERPRISE = 'enterprise';

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
            'settings'       => 'array',
            'is_active'      => 'boolean',
            'trial_ends_at'  => 'datetime',
        ];
    }

    protected static function booted(): void {
        static::creating(function (Organization $org): void {
            if (! $org->slug) {
                $base = Str::slug($org->name) ?: 'org';
                $slug = $base;
                $i    = 2;
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
}
