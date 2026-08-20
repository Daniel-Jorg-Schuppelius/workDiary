<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierMergeDismissal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Ein vom Anwender als „kein Duplikat" markiertes Lieferanten-Paar (Audit
 * 2026-08, W2.3). Das Paar ist normalisiert (low_id < high_id), damit die
 * Reihenfolge der beiden Lieferanten keine Rolle spielt.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $supplier_low_id
 * @property int $supplier_high_id
 * @property int|null $dismissed_by
 */
class SupplierMergeDismissal extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'supplier_low_id',
        'supplier_high_id',
        'dismissed_by',
    ];

    /**
     * Normalisierter Schlüssel für ein Lieferanten-Paar (kleinere ID zuerst).
     *
     * @return array{supplier_low_id: int, supplier_high_id: int}
     */
    public static function pairKey(int $a, int $b): array {
        return [
            'supplier_low_id' => min($a, $b),
            'supplier_high_id' => max($a, $b),
        ];
    }
}
