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

trait HasAttachments {
    /** @return MorphMany<Attachment, static> */
    public function attachments(): MorphMany {
        /** @var MorphMany<Attachment, static> $relation */
        $relation = $this->morphMany(Attachment::class, 'attachable')->latest();

        return $relation;
    }

    /**
     * Liefert den jüngsten Anhang zu einer Spezialrolle (meta_type),
     * z. B. 'logo', 'logo_dark', 'avatar'. Bei mehreren vorhandenen
     * Einträgen wird der zuletzt erstellte zurückgegeben.
     */
    public function attachmentByMeta(string $metaType): ?Attachment {
        /** @var Attachment|null $found */
        $found = $this->attachments()
            ->where('meta_type', $metaType)
            ->first();

        return $found;
    }
}
