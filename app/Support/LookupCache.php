<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LookupCache.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class LookupCache {
    private const TAG_OPTIONS_KEY = 'lookup.tags.options';

    private const USER_DROPDOWN_KEY = 'lookup.users.dropdown';

    /** @return Collection<int, Tag> */
    public static function tagOptions(): Collection {
        $tags = Cache::get(self::TAG_OPTIONS_KEY);

        if (! $tags instanceof Collection) {
            Cache::forget(self::TAG_OPTIONS_KEY);
            $tags = Tag::query()->orderBy('name')->get(['id', 'name', 'slug', 'color']);
            Cache::put(self::TAG_OPTIONS_KEY, $tags, now()->addMinutes(5));
        }

        /** @var Collection<int, Tag> $tags */
        return $tags;
    }

    /** @return Collection<int, User> */
    public static function userDropdown(): Collection {
        $users = Cache::get(self::USER_DROPDOWN_KEY);

        if (! $users instanceof Collection) {
            Cache::forget(self::USER_DROPDOWN_KEY);
            $users = User::query()->orderBy('name')->get(['id', 'name']);
            Cache::put(self::USER_DROPDOWN_KEY, $users, now()->addMinutes(10));
        }

        /** @var Collection<int, User> $users */
        return $users;
    }

    public static function forgetTagOptions(): void {
        Cache::forget(self::TAG_OPTIONS_KEY);
    }

    public static function forgetUserDropdown(): void {
        Cache::forget(self::USER_DROPDOWN_KEY);
    }
}
