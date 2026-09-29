<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleLicenseProduct.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lizenzprodukt des Lizenzbestands (Feature 152, MVP-1024): nur die
 * Bestandsmerkmale — Schlüsselvorlage und Meldebestand. Der verkaufsfähige
 * Artikel hängt über den Artikelkatalog an (`article_ref`, MVP-1025) —
 * Artikelstamm oder ein angebundenes Buchhaltungsprogramm, nie über eine
 * anbieterbezogene Spalte.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $manufacturer
 * @property list<array{code: string, label: string}> $key_roles
 * @property int|null $reorder_level null = kein Nachbestellhinweis
 * @property string|null $article_ref Katalogschlüssel (`art:<id>`, `lex:<id>`, MVP-1025)
 * @property string|null $note
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ResaleLicenseBatch> $batches
 */
class ResaleLicenseProduct extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'manufacturer',
        'key_roles',
        'reorder_level',
        'article_ref',
        'note',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'key_roles' => 'array',
        'reorder_level' => 'integer',
    ];

    /** @return HasMany<ResaleLicenseBatch, $this> */
    public function batches(): HasMany {
        return $this->hasMany(ResaleLicenseBatch::class, 'product_id');
    }

    /** @return list<array{code: string, label: string}> */
    public function keyRoles(): array {
        return $this->key_roles;
    }
}
