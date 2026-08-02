<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Disposal;

use App\Models\Asset;
use App\Models\Concerns\{HasAttachments, HasSqid};
use Database\Factories\Disposal\DisposalItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Geräteposition einer Entsorgungsakte (Feature 100, MVP-469).
 * `is_hazardous` wird ausschließlich aus dem AVV-Schlüssel abgeleitet
 * (CommonToolkit\ValueObjects\WasteCode, DisposalJobService). Fotos hängen
 * als polymorphe Attachments an der Position. Mandantengrenze transitiv
 * über disposal_jobs (Allow-List im TenantTraitCoverageTest).
 *
 * @property int $id
 * @property int $disposal_job_id
 * @property int $sort_order
 * @property string $category
 * @property string|null $manufacturer
 * @property string|null $model
 * @property string|null $serial_number
 * @property int $quantity
 * @property numeric-string|null $weight_kg
 * @property string|null $condition_note
 * @property string $avv_code
 * @property bool $is_hazardous
 * @property bool $has_data_storage
 * @property int|null $asset_id
 * @property string|null $note
 */
class DisposalItem extends Model {
    use HasAttachments;

    /** @use HasFactory<DisposalItemFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'disposal_job_id', 'sort_order', 'category', 'manufacturer', 'model',
        'serial_number', 'quantity', 'weight_kg', 'condition_note', 'avv_code',
        'is_hazardous', 'has_data_storage', 'asset_id', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sort_order' => 'integer',
        'quantity' => 'integer',
        'weight_kg' => 'decimal:3',
        'is_hazardous' => 'boolean',
        'has_data_storage' => 'boolean',
    ];

    /** @return BelongsTo<DisposalJob, $this> */
    public function job(): BelongsTo {
        return $this->belongsTo(DisposalJob::class, 'disposal_job_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return HasMany<DataMediaTreatment, $this> */
    public function treatments(): HasMany {
        return $this->hasMany(DataMediaTreatment::class)->orderBy('treated_at')->orderBy('id');
    }
}
