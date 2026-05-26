<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Aggregierte Export-Zeile je User × Lohnart × Kostenstelle (MVP-019).
 *
 * @property int $id
 * @property int $time_export_id
 * @property int $user_id
 * @property string $wage_type
 * @property string|null $cost_center
 * @property string $quantity
 * @property string $unit
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string|null $note
 * @property array<int, mixed>|null $source_refs
 */
class TimeExportLine extends Model {
    protected $fillable = [
        'time_export_id',
        'user_id',
        'wage_type',
        'cost_center',
        'quantity',
        'unit',
        'period_start',
        'period_end',
        'note',
        'source_refs',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'source_refs' => 'array',
        'quantity' => 'decimal:4',
    ];

    /** @return BelongsTo<TimeExport, $this> */
    public function export(): BelongsTo {
        return $this->belongsTo(TimeExport::class, 'time_export_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
