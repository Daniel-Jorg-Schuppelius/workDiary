<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetDefect.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Asset\{DefectSeverity, DefectStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\AssetDefectFactory;
use Illuminate\Database\Eloquent\{Builder, Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Defektmeldung / Sperrstatus eines Assets (Feature 009).
 *
 * Ein offener Defekt (Status open/inRepair) mit blocks_usage = true sperrt das
 * Asset für die Ausgabe. Die Sperre wird vom AssetAssignmentService aus den
 * offenen blockierenden Defekten abgeleitet (siehe AssetService::isBlocked()).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_id
 * @property int|null $reported_by_user_id
 * @property Carbon $reported_at
 * @property DefectSeverity $severity
 * @property string $title
 * @property string|null $description
 * @property DefectStatus $status
 * @property bool $blocks_usage
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by_user_id
 * @property string|null $resolution_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AssetDefect extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<AssetDefectFactory> */
    use HasFactory;

    use HasSqid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'asset_id',
        'reported_by_user_id',
        'reported_at',
        'severity',
        'title',
        'description',
        'status',
        'blocks_usage',
        'resolved_at',
        'resolved_by_user_id',
        'resolution_note',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'severity' => DefectSeverity::class,
        'status' => DefectStatus::class,
        'blocks_usage' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reportedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    /** Sperrt diese Meldung das Asset aktuell? (offen + blocks_usage) */
    public function isBlocking(): bool {
        return $this->blocks_usage && $this->status->isOpen();
    }

    /**
     * @param  Builder<AssetDefect>  $query
     * @return Builder<AssetDefect>
     */
    public function scopeBlocking(Builder $query): Builder {
        return $query
            ->where('blocks_usage', true)
            ->whereIn('status', [DefectStatus::Open->value, DefectStatus::InRepair->value]);
    }
}
