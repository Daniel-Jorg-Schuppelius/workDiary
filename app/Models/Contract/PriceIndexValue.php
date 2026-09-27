<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceIndexValue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contract;

use App\Enums\Contract\PriceIndexStatus;
use App\Models\Concerns\HasSqid;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Monatswert einer Preisindexreihe (MVP-952); installationsweite
 * Referenzdaten, erst nach Freigabe für Anpassungen maßgeblich.
 *
 * @property int $id
 * @property string $series
 * @property Carbon $period_on
 * @property numeric-string $value
 * @property string $source
 * @property PriceIndexStatus $status
 * @property int|null $approver_user_id
 * @property Carbon|null $approved_at
 */
class PriceIndexValue extends Model {
    use HasSqid;

    public const SERIES_VPI = 'vpi';

    protected $fillable = ['series', 'period_on', 'value', 'source', 'status', 'approver_user_id', 'approved_at'];

    /** @var array<string, string> */
    protected $casts = [
        'period_on' => 'date',
        'value' => 'decimal:1',
        'status' => PriceIndexStatus::class,
        'approved_at' => 'datetime',
    ];

    /** @param Builder<self> $query */
    public function scopeApproved(Builder $query): void {
        $query->where('status', PriceIndexStatus::Approved->value);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
