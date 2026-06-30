<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectMergeDismissal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Ein vom Anwender als „kein Duplikat" markiertes Projekt-Paar. Das Paar ist
 * normalisiert (low_id < high_id), damit die Reihenfolge keine Rolle spielt.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $project_low_id
 * @property int $project_high_id
 * @property int|null $dismissed_by
 */
class ProjectMergeDismissal extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'project_low_id',
        'project_high_id',
        'dismissed_by',
    ];

    /**
     * Normalisierter Schlüssel für ein Projekt-Paar (kleinere ID zuerst).
     *
     * @return array{project_low_id: int, project_high_id: int}
     */
    public static function pairKey(int $a, int $b): array {
        return [
            'project_low_id' => min($a, $b),
            'project_high_id' => max($a, $b),
        ];
    }
}
