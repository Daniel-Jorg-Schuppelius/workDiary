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
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Protokoll eines GAEB-DA-XML-Importlaufs (Feature 049, MVP-081). Speichert
 * Datei-Hash, erkannte Version/Phase und den Preflight-Befund. Ein Lauf mit
 * blockierenden Befunden schreibt keine LV-Positionen.
 *
 * Aus dem Paketeingang (MVP-627) entstehen Läufe im Zustand `pending`: Die
 * Datei liegt unter `stored_path` bereit, importiert wird erst auf Zuruf.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $bill_of_quantity_id
 * @property int|null $application_opportunity_id
 * @property string $filename
 * @property string $file_hash
 * @property string|null $stored_path
 * @property string|null $package_name
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
    use HasSqid;

    protected $fillable = [
        'source_format',
        'organization_id',
        'bill_of_quantity_id',
        'application_opportunity_id',
        'filename',
        'file_hash',
        'stored_path',
        'package_name',
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

    /**
     * Der Vergabevorgang, aus dessen Paket der Lauf stammt (MVP-627).
     *
     * @return BelongsTo<\App\Models\Applications\ApplicationOpportunity, $this>
     */
    public function opportunity(): BelongsTo {
        return $this->belongsTo(\App\Models\Applications\ApplicationOpportunity::class, 'application_opportunity_id');
    }

    /** @return BelongsTo<BillOfQuantity, $this> */
    public function billOfQuantity(): BelongsTo {
        return $this->belongsTo(BillOfQuantity::class);
    }
}
