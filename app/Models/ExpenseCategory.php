<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $slug
 * @property string $label
 * @property string|null $icon
 * @property string $color
 * @property string|null $description
 * @property string $default_tax_rate
 * @property bool $default_billable
 * @property bool $requires_receipt
 * @property int $sort
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExpenseCategory extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<ExpenseCategoryFactory> */
    use HasFactory;

    public const SLUG_MEALS = 'meals';

    public const SLUG_LODGING = 'lodging';

    public const SLUG_HOSPITALITY = 'hospitality';

    public const SLUG_TICKETS = 'tickets';

    public const SLUG_PARKING = 'parking';

    public const SLUG_SUPPLIES = 'supplies';

    public const SLUG_TELECOM = 'telecom';

    public const SLUG_OTHER = 'other';

    protected $fillable = [
        'organization_id',
        'slug',
        'label',
        'icon',
        'color',
        'description',
        'default_tax_rate',
        'default_billable',
        'requires_receipt',
        'sort',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'default_tax_rate' => 'decimal:2',
        'default_billable' => 'boolean',
        'requires_receipt' => 'boolean',
        'sort' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany {
        return $this->hasMany(Expense::class);
    }

    /**
     * @param  Builder<ExpenseCategory>  $query
     * @return Builder<ExpenseCategory>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<ExpenseCategory>  $query
     * @return Builder<ExpenseCategory>
     */
    public function scopeOrdered(Builder $query): Builder {
        return $query->orderBy('sort')->orderBy('label');
    }
}
