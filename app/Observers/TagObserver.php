<?php

namespace App\Observers;

use App\Models\Tag;
use App\Support\LookupCache;

class TagObserver
{
    public function saved(Tag $tag): void
    {
        LookupCache::forgetTagOptions();
    }

    public function deleted(Tag $tag): void
    {
        LookupCache::forgetTagOptions();
    }
}
