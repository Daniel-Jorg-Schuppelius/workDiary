<?php

namespace App\Support;

use App\Models\Legacy\LegacyUser;
use App\Models\User;

class LegacyRoleResolver {
    public static function resolveLegacyUserId(?User $authUser): int {
        if (! $authUser instanceof User) {
            return 0;
        }

        $legacyUserId = (int) ($authUser->legacy_user_id ?? 0);
        if ($legacyUserId > 0) {
            return $legacyUserId;
        }

        if (! filled(config('database.connections.legacy.database'))) {
            return 0;
        }

        $candidates = self::candidateUsernames($authUser);

        if ($candidates === []) {
            return 0;
        }

        $legacy = LegacyUser::query()
            ->whereIn('uname', $candidates)
            ->orderBy('id')
            ->first(['id']);

        if (! $legacy) {
            return 0;
        }

        $authUser->legacy_user_id = (int) $legacy->id;
        $authUser->save();

        return (int) $legacy->id;
    }

    public static function isFallbackAdmin(?User $authUser): bool {
        if (! $authUser instanceof User) {
            return false;
        }

        $configured = (string) env('LEGACY_FALLBACK_ADMINS', 'admin,administrator,chef');
        $allowed = array_values(array_filter(array_map(
            static fn (string $value): string => strtolower(trim($value)),
            explode(',', $configured)
        )));

        if ($allowed === []) {
            return false;
        }

        foreach (self::candidateUsernames($authUser) as $candidate) {
            if (in_array(strtolower($candidate), $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    public static function isAdmin(?User $authUser): bool {
        $legacyUserId = self::resolveLegacyUserId($authUser);

        return ($legacyUserId > 0 && $legacyUserId <= 3) || self::isFallbackAdmin($authUser);
    }

    /**
     * @return array<int, string>
     */
    private static function candidateUsernames(User $authUser): array {
        return array_values(array_filter(array_unique([
            trim((string) $authUser->name),
            trim((string) strstr((string) $authUser->email, '@', true)),
        ])));
    }
}