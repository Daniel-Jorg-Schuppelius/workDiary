<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillOfQuantity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Gaeb\{BoqItemStatus, GaebPhase};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Leistungsverzeichnis-Kopf (Feature 049, MVP-082). Bündelt Abschnitte und
 * Positionen eines GAEB-Imports und bindet sie optional an Projekt/Auftrag.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $project_id
 * @property int|null $diary_entry_id
 * @property string $name
 * @property string|null $external_id
 * @property string|null $gaeb_version
 * @property array<int, array{no: int, label: ?string, category: ?string}>|null $up_components
 * @property array<string, string|null>|null $totals
 * @property GaebPhase|null $phase
 * @property \CommonToolkit\Enums\CurrencyCode $currency
 * @property BoqItemStatus $status
 */
class BillOfQuantity extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'project_id',
        'diary_entry_id',
        'name',
        'external_id',
        'gaeb_version',
        'up_components',
        'totals',
        'phase',
        'currency',
        'status',
        'created_by',
    ];

    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'phase' => GaebPhase::class,
        'status' => BoqItemStatus::class,
        'up_components' => 'array',
        'totals' => 'array',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return HasMany<BoqSection, $this> */
    public function sections(): HasMany {
        return $this->hasMany(BoqSection::class);
    }

    /** @return HasMany<BoqItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(BoqItem::class);
    }

    /** @return HasMany<GaebImport, $this> */
    public function imports(): HasMany {
        return $this->hasMany(GaebImport::class);
    }

    /** @return HasMany<BoqExport, $this> */
    public function exports(): HasMany {
        return $this->hasMany(BoqExport::class);
    }
}
