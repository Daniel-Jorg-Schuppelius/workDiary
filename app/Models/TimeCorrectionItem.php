<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeCorrectionItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine einzelne Änderung innerhalb eines Korrekturantrags (MVP-017).
 *
 * @property int $id
 * @property int $time_correction_request_id
 * @property string $target_type
 * @property int|null $target_id
 * @property string $action
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 */
class TimeCorrectionItem extends Model {
    public $timestamps = false;

    protected $fillable = [
        'time_correction_request_id',
        'target_type',
        'target_id',
        'action',
        'before',
        'after',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    /** @return BelongsTo<TimeCorrectionRequest, $this> */
    public function request(): BelongsTo {
        return $this->belongsTo(TimeCorrectionRequest::class, 'time_correction_request_id');
    }
}
