<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyEventResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Resources;

use App\Models\{SafetyEvent, User};
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SafetyEvent */
class SafetyEventResource extends JsonResource {
    public function __construct(SafetyEvent $resource) {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        return [
            'id' => $this->sqid,
            'event_no' => $this->event_no,
            'display_no' => $this->displayNo(),
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'severity' => $this->severity->value,
            'severity_label' => $this->severity->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'occurred_at' => optional($this->occurred_at)->toIso8601String(),
            'location' => $this->location,
            'affected_person' => $this->affected_person,
            'description' => $this->description,
            'immediate_action' => $this->immediate_action,
            'root_cause' => $this->root_cause,
            'reported_by' => Sqid::encodeOrNull(User::class, $this->reported_by_user_id),
            'closed_at' => optional($this->closed_at)->toIso8601String(),
            'closed_by' => Sqid::encodeOrNull(User::class, $this->closed_by_user_id),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
