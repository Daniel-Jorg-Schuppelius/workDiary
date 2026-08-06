<?php
/*
 * Created on   : Wed Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialCostAllocation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Database\Factories\MaterialCostAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Einem Kunden (optional Projekt) zugeordneter Materialkosten-Betrag — anteilig
 * aus einem Lexoffice-Einkaufsbeleg (morph `source`) oder als freier manueller
 * Betrag (`source` NULL). Grundlage der Gewinndarstellung (Umsatz − Material).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property int|null $project_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $description
 * @property Money|null $allocated_amount
 * @property CurrencyCode $currency
 * @property Carbon $allocated_on
 * @property int|null $created_by
 */
class MaterialCostAllocation extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<MaterialCostAllocationFactory> */
    use HasFactory;
    use HasSqid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'project_id',
        'source_type',
        'source_id',
        'description',
        'allocated_amount',
        'currency',
        'allocated_on',
        'created_by',
    ];

    protected function casts(): array {
        return [
            'currency' => CurrencyCode::class,
            'allocated_amount' => MoneyCast::class . ':currency,2',
            'allocated_on' => 'date',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }
}
