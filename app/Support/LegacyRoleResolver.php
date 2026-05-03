<?php

namespace App\Support;

use App\Models\Legacy\LegacyUser;
use App\Models\User;

class LegacyRoleResolver {
    /** Per-Request-Cache: user-id → resolved legacy-id
     * @var array<int, int>
     */
    private static array $idCache = [];

    /** Memoized allowed fallback-admin list for this request
     * @var list<string>|null
     */
    private static ?array $fallbackList = null;

    public static function resolveLegacyUserId(?User $authUser): int {
        if (! $authUser instanceof User) {
            return 0;
        }

        if (isset(self::$idCache[$authUser->id])) {
            return self::$idCache[$authUser->id];
        }

        $legacyUserId = (int) ($authUser->legacy_user_id ?? 0);
        if ($legacyUserId > 0) {
            return self::$idCache[$authUser->id] = $legacyUserId;
        }

        if (! filled(config('database.connections.legacy.database'))) {
            return self::$idCache[$authUser->id] = 0;
        }

        $candidates = self::candidateUsernames($authUser);

        if ($candidates === []) {
            return self::$idCache[$authUser->id] = 0;
        }

        $legacy = LegacyUser::query()
            ->whereIn('uname', $candidates)
            ->orderBy('id')
            ->first(['id']);

        if (! $legacy) {
            return self::$idCache[$authUser->id] = 0;
        }

        $authUser->legacy_user_id = (int) $legacy->id;
        $authUser->save();

        return self::$idCache[$authUser->id] = (int) $legacy->id;
    }

    /**
     * Gibt true zurück, wenn der User eine Legacy-ID ≤ 3 hat.
     * Berücksichtigt KEINE Namens-Fallbacks — für sicherheitskritische Prüfungen bevorzugen.
     */
    public static function isAdminByLegacyId(?User $authUser): bool {
        $legacyUserId = self::resolveLegacyUserId($authUser);

        return $legacyUserId > 0 && $legacyUserId <= 3;
    }

    /**
     * Gibt true zurück, wenn der Username des Users in der konfigurierten
     * Fallback-Admin-Liste (LEGACY_FALLBACK_ADMINS) steht.
     * Nur als Übergangsmechanismus gedacht; sobald alle Admins eine Legacy-ID haben,
     * kann dieser Pfad deaktiviert werden.
     */
    public static function isFallbackAdmin(?User $authUser): bool {
        if (! $authUser instanceof User) {
            return false;
        }

        $allowed = self::fallbackAdminList();

        if ($allowed === []) {
            return false;
        }

        foreach (self::candidateUsernames($authUser) as $candidate) {
            if (in_array(mb_strtolower($candidate), $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    /** Prüft Legacy-Admin-Status über ID (primär) oder Namens-Fallback (sekundär). */
    public static function isAdmin(?User $authUser): bool {
        return self::isAdminByLegacyId($authUser) || self::isFallbackAdmin($authUser);
    }

    /** Memoized Fallback-Admin-Liste für den aktuellen Request.
     * @return list<string>
     */
    private static function fallbackAdminList(): array {
        if (self::$fallbackList !== null) {
            return self::$fallbackList;
        }

        $configured = (string) config('legacy.fallback_admins', 'admin,administrator,chef');

        return self::$fallbackList = array_values(array_filter(array_map(
            static fn(string $value): string => mb_strtolower(trim($value)),
            explode(',', $configured)
        )));
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
