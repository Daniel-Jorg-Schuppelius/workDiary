<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SalesDiscountGroup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Verkaufs-Rabattgruppe (Feature 107, W9): org-weite Standard-Kondition für
 * den DATANORM-Export mit Listenpreisen. `kind` = discount (Prozent), factor
 * (Multiplikator) oder surcharge (Prozent-Zuschlag); `value` trägt Prozentsatz
 * (20.0000 = 20 %) bzw. Faktor (0.9000). Der `code` (max. 4 Zeichen) landet im
 * A-Satz und in der RAB-Datei.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $kind
 * @property numeric-string $value
 * @property string|null $label
 */
class SalesDiscountGroup extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    // Audit 2026-08 (W3.3): Formulare/URLs tragen Sqids, nie rohe IDs.
    use HasSqid;

    public const KIND_DISCOUNT = 'discount';
    public const KIND_FACTOR = 'factor';
    public const KIND_SURCHARGE = 'surcharge';

    protected $fillable = [
        'organization_id',
        'code',
        'kind',
        'value',
        'label',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'value' => 'decimal:4',
    ];

    /** @return HasMany<Article, $this> */
    public function articles(): HasMany {
        return $this->hasMany(Article::class);
    }

    /** @return HasMany<SalesDiscountGroupOverride, $this> */
    public function overrides(): HasMany {
        return $this->hasMany(SalesDiscountGroupOverride::class);
    }
}
