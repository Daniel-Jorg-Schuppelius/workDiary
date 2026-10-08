<?php
/*
 * Created on   : Thu Oct 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftTypeResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Resources;

use App\Models\Schedule\ShiftType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Schichttyp, wie der Dienstplan ihn liest: Seitenkonfiguration und die
 * JSON-Antworten des eingebetteten Typ-Managers haben dieselbe Form.
 *
 * @mixin ShiftType
 */
class ShiftTypeResource extends JsonResource {
    public function __construct(ShiftType $resource) {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        return [
            'id' => $this->sqid,
            'name' => $this->name,
            'abbreviation' => $this->abbreviation,
            'color' => $this->color,
            'default_start_time' => $this->default_start_time,
            'default_end_time' => $this->default_end_time,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
