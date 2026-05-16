<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasAttachments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @method \Illuminate\Database\Eloquent\Relations\MorphMany<\App\Models\Attachment, static> morphMany(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null)
 */
trait HasAttachments {
    /** @return MorphMany<Attachment, static> */
    public function attachments(): MorphMany {
        /** @var MorphMany<Attachment, static> $relation */
        $relation = $this->morphMany(Attachment::class, 'attachable')->latest();

        return $relation;
    }
}
