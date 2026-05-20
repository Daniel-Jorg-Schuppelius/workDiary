<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TagObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Models\Tag;
use App\Support\LookupCache;

class TagObserver {
    public function saved(Tag $tag): void {
        LookupCache::forgetTagOptions();
    }

    public function deleted(Tag $tag): void {
        LookupCache::forgetTagOptions();
    }
}
