<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportRunError.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Import\ImportErrorCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * MVP-049 — Einzelner Zeilenfehler eines Import-Laufs.
 *
 * Tenant-Grenze wird transitiv über {@see ImportRun} durchgesetzt;
 * eigene organization_id wäre redundant (vgl. TimeExportEvent, MonthClosureEvent).
 *
 * @property int $id
 * @property int $import_run_id
 * @property int $row_number
 * @property string|null $field
 * @property ImportErrorCode $code
 * @property string $message
 * @property array<string, mixed>|null $row_data
 * @property Carbon $created_at
 */
class ImportRunError extends Model {
    public $timestamps = false;

    protected $fillable = [
        'import_run_id',
        'row_number',
        'field',
        'code',
        'message',
        'row_data',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'code' => ImportErrorCode::class,
        'row_data' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<ImportRun, $this> */
    public function run(): BelongsTo {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }
}
