<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebImport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Gaeb\{GaebImportStatus, GaebPhase};
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Protokoll eines GAEB-DA-XML-Importlaufs (Feature 049, MVP-081). Speichert
 * Datei-Hash, erkannte Version/Phase und den Preflight-Befund. Ein Lauf mit
 * blockierenden Befunden schreibt keine LV-Positionen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $bill_of_quantity_id
 * @property string $filename
 * @property string $file_hash
 * @property string|null $gaeb_version
 * @property GaebPhase|null $phase
 * @property GaebImportStatus $status
 * @property int $section_count
 * @property int $item_count
 * @property array<string, mixed>|null $preflight
 */
class GaebImport extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'source_format',
        'organization_id',
        'bill_of_quantity_id',
        'filename',
        'file_hash',
        'gaeb_version',
        'phase',
        'status',
        'section_count',
        'item_count',
        'preflight',
        'created_by',
    ];

    protected $casts = [
        'phase' => GaebPhase::class,
        'status' => GaebImportStatus::class,
        'section_count' => 'integer',
        'item_count' => 'integer',
        'preflight' => 'array',
    ];

    /** @return BelongsTo<BillOfQuantity, $this> */
    public function billOfQuantity(): BelongsTo {
        return $this->belongsTo(BillOfQuantity::class);
    }
}
