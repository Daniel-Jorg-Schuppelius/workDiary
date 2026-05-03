<?php

namespace App\Models\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @method \Illuminate\Database\Eloquent\Relations\MorphMany<\App\Models\Attachment, static> morphMany(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null)
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait HasAttachments {
    /** @return MorphMany<Attachment, static> */
    public function attachments(): MorphMany {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }
}
