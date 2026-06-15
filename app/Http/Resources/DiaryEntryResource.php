<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryEntryResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Resources;

use App\Models\DiaryEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DiaryEntry */
class DiaryEntryResource extends JsonResource {
    public function __construct(DiaryEntry $resource) {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array {
        return [
            'id' => $this->sqid,
            'user' => new UserResource($this->whenLoaded('user')),
            'content' => $this->content,
            'response' => $this->response,
            'status' => $this->status,
            'status_key' => $this->status->slug(),
            'status_label' => $this->statusLabel(),
            'lifecycle' => [
                'accepted_at' => optional($this->accepted_at)->toIso8601String(),
                'started_at' => optional($this->started_at)->toIso8601String(),
                'paused_at' => optional($this->paused_at)->toIso8601String(),
                'resumed_at' => optional($this->resumed_at)->toIso8601String(),
                'wait_seconds_total' => (int) $this->wait_seconds_total,
                'completed_at' => optional($this->completed_at)->toIso8601String(),
                'accepted_final_at' => optional($this->accepted_final_at)->toIso8601String(),
                'invoiced_at' => optional($this->invoiced_at)->toIso8601String(),
                'cancelled_at' => optional($this->cancelled_at)->toIso8601String(),
            ],
            'is_archived' => (bool) $this->is_archived,
            'start_at' => optional($this->start_at)->toIso8601String(),
            'end_at' => optional($this->end_at)->toIso8601String(),
            'archived_at' => optional($this->archived_at)->toIso8601String(),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
