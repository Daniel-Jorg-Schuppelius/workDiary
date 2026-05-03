<?php

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
        /** @var Collection<int, Tag> $tags */
        $tags = Cache::remember(
            self::TAG_OPTIONS_KEY,
            now()->addMinutes(5),
            static fn() =>
            Tag::query()->orderBy('name')->get(['id', 'name', 'slug', 'color'])
        );

        return $tags;
    }

    /** @return Collection<int, User> */
    public static function userDropdown(): Collection {
        /** @var Collection<int, User> $users */
        $users = Cache::remember(
            self::USER_DROPDOWN_KEY,
            now()->addMinutes(10),
            static fn() =>
            User::query()->orderBy('name')->get(['id', 'name'])
        );

        return $users;
    }

    public static function forgetTagOptions(): void {
        Cache::forget(self::TAG_OPTIONS_KEY);
    }

    public static function forgetUserDropdown(): void {
        Cache::forget(self::USER_DROPDOWN_KEY);
    }
}
