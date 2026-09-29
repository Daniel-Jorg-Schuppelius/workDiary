<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleLicenseBatch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Supplier\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Einkaufspaket (MVP-1024): gekaufte Menge eines Lizenzprodukts mit der beim
 * Kauf eingefrorenen Schlüsselvorlage. Die Menge wird nach der Anlage nicht
 * mehr geändert — Nachkäufe sind neue Pakete.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $product_id
 * @property string $reference
 * @property CarbonImmutable $purchased_on
 * @property int|null $supplier_id
 * @property string|null $supplier_name
 * @property int $quantity
 * @property list<array{code: string, label: string}> $key_roles
 * @property int $key_count
 * @property string|null $document_type
 * @property int|null $document_id
 * @property string|null $document_reference
 * @property string|null $note
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read ResaleLicenseProduct $product
 * @property-read Supplier|null $supplier
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ResaleLicenseUnit> $units
 */
class ResaleLicenseBatch extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'product_id',
        'reference',
        'purchased_on',
        'supplier_id',
        'supplier_name',
        'quantity',
        'key_roles',
        'key_count',
        'document_type',
        'document_id',
        'document_reference',
        'note',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'purchased_on' => 'immutable_date',
        'quantity' => 'integer',
        'key_roles' => 'array',
        'key_count' => 'integer',
        'document_id' => 'integer',
    ];

    /** @return BelongsTo<ResaleLicenseProduct, $this> */
    public function product(): BelongsTo {
        return $this->belongsTo(ResaleLicenseProduct::class, 'product_id');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<ResaleLicenseUnit, $this> */
    public function units(): HasMany {
        return $this->hasMany(ResaleLicenseUnit::class, 'batch_id');
    }

    /** @return list<array{code: string, label: string}> */
    public function keyRoles(): array {
        return $this->key_roles;
    }

    public function supplierLabel(): ?string {
        return $this->supplier->name ?? $this->supplier_name;
    }
}
