<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string|null $sku
 * @property string $name
 * @property string $unit
 * @property string|null $default_unit_price
 * @property string|null $tax_rate
 * @property string|null $external_provider
 * @property string|null $external_id
 * @property bool $is_active
 */
class Material extends Model
{
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'sku',
        'name',
        'unit',
        'default_unit_price',
        'tax_rate',
        'external_provider',
        'external_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_unit_price' => 'decimal:4',
            'tax_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
