<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerMergeDismissal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Ein vom Anwender als „kein Duplikat" markiertes Kunden-Paar. Das Paar ist
 * normalisiert (low_id < high_id), damit die Reihenfolge der beiden Kunden
 * keine Rolle spielt.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $customer_low_id
 * @property int $customer_high_id
 * @property int|null $dismissed_by
 */
class CustomerMergeDismissal extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'customer_low_id',
        'customer_high_id',
        'dismissed_by',
    ];

    /**
     * Normalisierter Schlüssel für ein Kunden-Paar (kleinere ID zuerst).
     *
     * @return array{customer_low_id: int, customer_high_id: int}
     */
    public static function pairKey(int $a, int $b): array {
        return [
            'customer_low_id' => min($a, $b),
            'customer_high_id' => max($a, $b),
        ];
    }
}
