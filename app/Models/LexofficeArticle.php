<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeArticle.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Lokaler Cache eines Lexoffice-Artikels (Service oder Produkt).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $external_id
 * @property string $name
 * @property ?string $article_number
 * @property ?string $description
 * @property string $type
 * @property ?string $unit_name
 * @property ?string $net_unit_price
 * @property string $currency
 * @property ?string $vat_rate
 * @property ?Carbon $synced_at
 * @property ?Carbon $archived_at
 */
class LexofficeArticle extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'external_id',
        'name',
        'article_number',
        'description',
        'type',
        'unit_name',
        'net_unit_price',
        'currency',
        'vat_rate',
        'synced_at',
        'archived_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'net_unit_price' => 'decimal:4',
        'vat_rate' => 'decimal:2',
        'synced_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->whereNull('archived_at');
    }
}
