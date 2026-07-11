<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\Asset\AssetBlockReason;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

/**
 * Gemeinsame Asset-Sperre (Entscheidung D12): Verleih, Disposition und
 * Prüfwesen lesen denselben Sperrstatus. Die Quelle der Sperre wird als
 * Referenz geführt; Ausnahmefreigaben sind befristet und auditiert.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_id
 * @property AssetBlockReason $reason
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $note
 * @property \Illuminate\Support\Carbon $blocked_from
 * @property \Illuminate\Support\Carbon|null $blocked_until
 * @property \Illuminate\Support\Carbon|null $released_at
 */
class AssetBlock extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'asset_id', 'reason', 'source_type', 'source_id',
        'note', 'blocked_from', 'blocked_until', 'created_by',
        'released_at', 'released_by', 'release_note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'reason' => AssetBlockReason::class,
        'blocked_from' => 'datetime',
        'blocked_until' => 'date',
        'released_at' => 'datetime',
    ];

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void {
        $query->whereNull('released_at')
            ->where(function (Builder $q): void {
                $q->whereNull('blocked_until')->orWhereDate('blocked_until', '>=', now()->toDateString());
            });
    }

    public function isActive(): bool {
        return $this->released_at === null
            && ($this->blocked_until === null || $this->blocked_until->endOfDay()->isFuture());
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo {
        return $this->morphTo();
    }

    /** @return HasMany<AssetBlockException, $this> */
    public function exceptions(): HasMany {
        return $this->hasMany(AssetBlockException::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function releaser(): BelongsTo {
        return $this->belongsTo(User::class, 'released_by');
    }

    /**
     * Gültige (nicht widerrufene, nicht abgelaufene) Ausnahme für einen Kontext.
     */
    public function activeExceptionFor(string $context): ?AssetBlockException {
        return $this->exceptions
            ->first(fn (AssetBlockException $exception): bool => $exception->coversContext($context));
    }
}
