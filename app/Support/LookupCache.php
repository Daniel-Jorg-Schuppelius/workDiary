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

use App\Models\{Organization, Tag, User};
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

    /**
     * Mitarbeiter-Dropdown der AKTUELLEN Organisation. Der Cache-Key ist
     * bewusst je Organisation getrennt: das User-Modell trägt keinen globalen
     * OrganizationScope (tenant-audit-2026.md), und ein einzelner globaler Key
     * hätte die Mitarbeiter einer Organisation an alle anderen ausgeliefert.
     *
     * @return Collection<int, User>
     */
    public static function userDropdown(): Collection {
        $key = self::userDropdownKey(self::currentOrganizationId());
        $users = Cache::get($key);

        if (! $users instanceof Collection) {
            Cache::forget($key);
            $users = User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']);
            Cache::put($key, $users, now()->addMinutes(10));
        }

        /** @var Collection<int, User> $users */
        return $users;
    }

    public static function forgetTagOptions(): void {
        Cache::forget(self::TAG_OPTIONS_KEY);
    }

    /**
     * Invalidiert das Mitarbeiter-Dropdown der angegebenen Organisation
     * (Default: aktuell gebundene Organisation). Der {@see \App\Observers\UserObserver}
     * übergibt die Organisation des betroffenen Users, damit die Invalidierung
     * unabhängig vom Request-Org-Kontext greift.
     */
    public static function forgetUserDropdown(?int $organizationId = null): void {
        Cache::forget(self::userDropdownKey($organizationId ?? self::currentOrganizationId()));
    }

    private static function userDropdownKey(int $organizationId): string {
        return self::USER_DROPDOWN_KEY . '.' . $organizationId;
    }

    private static function currentOrganizationId(): int {
        if (app()->bound('currentOrganization')) {
            $organization = app('currentOrganization');
            if ($organization instanceof Organization) {
                return (int) $organization->id;
            }
        }

        return 0;
    }
}
