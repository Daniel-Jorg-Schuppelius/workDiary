<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialUsage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $timesheet_id
 * @property int|null $material_id
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property string|null $unit_price
 * @property string|null $tax_rate
 * @property string $line_total_net
 */
class MaterialUsage extends Model {
    use Auditable;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'timesheet_id',
        'material_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'tax_rate',
        'line_total_net',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'line_total_net' => 'decimal:2',
    ];

    protected static function booted(): void {
        static::saving(function (MaterialUsage $usage): void {
            $qty = (float) $usage->quantity;
            $price = (float) ($usage->unit_price ?? 0);
            $usage->line_total_net = (string) round($qty * $price, 2);
        });
    }

    /** @return BelongsTo<Timesheet, $this> */
    public function timesheet(): BelongsTo {
        return $this->belongsTo(Timesheet::class);
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo {
        return $this->belongsTo(Material::class);
    }
}
