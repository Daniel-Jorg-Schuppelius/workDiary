<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentResource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Attachment */
class AttachmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attachable_type' => class_basename($this->attachable_type),
            'attachable_id' => $this->attachable_id,
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'uploader' => new UserResource($this->whenLoaded('uploader')),
            'download_url' => route('attachments.download', $this->resource),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
