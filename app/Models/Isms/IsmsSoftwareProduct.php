<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSoftwareProduct.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\{SoftwareCategory, SupportStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Database\Factories\Isms\IsmsSoftwareProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Softwareprodukt im organisationsbezogenen Softwareinventar
 * (Feature 044, MVP 1, Ebene 1): Produkt + „führende" Version,
 * Verantwortlicher, Support-Status und End-of-Life-Datum. Die konkreten
 * Einsatzorte hängen als {@see IsmsSoftwareInstallation} daran. Die
 * produktbezogene WorkDiary-SBOM (Ebene 2) ist bewusst getrennt
 * (Services\Isms\SbomGenerator).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $vendor
 * @property string|null $product_version
 * @property SoftwareCategory|null $category
 * @property int|null $owner_user_id
 * @property SupportStatus $support_status
 * @property Carbon|null $eol_on
 * @property string|null $notes
 */
class IsmsSoftwareProduct extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsSoftwareProductFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'vendor',
        'product_version',
        'category',
        'owner_user_id',
        'support_status',
        'eol_on',
        'notes',
    ];

    protected $casts = [
        'category' => SoftwareCategory::class,
        'support_status' => SupportStatus::class,
        'eol_on' => 'date',
    ];

    /** @return HasMany<IsmsSoftwareInstallation, $this> */
    public function installations(): HasMany {
        return $this->hasMany(IsmsSoftwareInstallation::class, 'isms_software_product_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Produkte, deren End-of-Life-Datum erreicht oder überschritten ist.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeEolReached(Builder $query): Builder {
        return $query
            ->whereNotNull('eol_on')
            ->whereDate('eol_on', '<=', now()->toDateString());
    }

    /** EOL erreicht/überschritten? (Listen-Badge) */
    public function eolReached(): bool {
        return $this->eol_on !== null && $this->eol_on->startOfDay()->lte(now()->startOfDay());
    }

    /** EOL steht innerhalb der nächsten 90 Tage an (Warn-Badge in der Liste). */
    public function eolSoon(): bool {
        return $this->eol_on !== null
            && ! $this->eolReached()
            && $this->eol_on->startOfDay()->lt(now()->startOfDay()->addDays(90));
    }
}
