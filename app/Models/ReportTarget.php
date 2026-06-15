<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Reporting\{ReportTargetMetric, ReportTargetPeriod, ReportTargetScope};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\CarbonInterface;
use Database\Factories\ReportTargetFactory;
use Illuminate\Database\Eloquent\{Builder, Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Feature 002 (Zielwerte & Benchmarks): Soll-Wert für eine Kennzahl, optional
 * eingegrenzt auf Kunde/Projekt/Mitarbeitenden und einen Gültigkeitszeitraum.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property ReportTargetMetric $metric
 * @property ReportTargetScope $scope
 * @property int|null $scope_id
 * @property float $target_value
 * @property ReportTargetPeriod|null $period
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReportTarget extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<ReportTargetFactory> */
    use HasFactory;

    use HasSqid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'metric',
        'scope',
        'scope_id',
        'target_value',
        'period',
        'valid_from',
        'valid_until',
        'note',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'metric' => ReportTargetMetric::class,
        'scope' => ReportTargetScope::class,
        'scope_id' => 'integer',
        'target_value' => 'decimal:2',
        'period' => ReportTargetPeriod::class,
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Nur Zielwerte, die zum Stichtag gültig sind (valid_from/valid_until offen
     * oder umschließend).
     *
     * @param  Builder<ReportTarget>  $query
     * @return Builder<ReportTarget>
     */
    public function scopeValidOn(Builder $query, CarbonInterface $date): Builder {
        $d = $date->toDateString();

        return $query
            ->where(fn(Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $d))
            ->where(fn(Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $d));
    }
}
