<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IpBanService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\Security\SecurityEventType;
use App\Models\Auth\SecurityIpBan;
use App\Models\Platform\User;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\IPHelper;
use Illuminate\Support\Facades\Cache;

/**
 * Temporäre IP-Sperre („fail2ban light“, Feature 096/097, MVP-450) für
 * Installationen ohne OS-Sperre. Standard aus (`SECURITY_IP_BAN`). Zählt
 * Fehlversuche je IP im Fenster; ab der Schwelle Sperre mit Staffel
 * 15 min → 1 h → 24 h (Stufe aus den Sperren der letzten sieben Tage).
 * Private Netze, Proxys und die Allowlist sperrt sie nie; Hinweisgeber-
 * Fehlversuche zählen nicht (keine IP in der Datenbank, HinSchG).
 */
final class IpBanService {
    private const COUNTED = [
        SecurityEventType::AuthFailed, SecurityEventType::AuthLockout, SecurityEventType::TwoFactorFailed,
        SecurityEventType::ApiTokenInvalid, SecurityEventType::WebhookSignatureInvalid, SecurityEventType::SsoFailed,
    ];

    public function enabled(): bool {
        return (bool) config('security.ip_ban.enabled', false);
    }

    public function record(SecurityEventType $type, string $ip): void {
        if (! $this->enabled() || ! in_array($type, self::COUNTED, true) || ! IPHelper::isValidIP($ip) || $this->allowlisted($ip)) {
            return;
        }

        $key = 'sec:fail:' . $ip;
        Cache::add($key, 0, now()->addMinutes((int) config('security.ip_ban.window_minutes', 10)));
        if ((int) Cache::increment($key) < (int) config('security.ip_ban.threshold', 20)) {
            return;
        }
        Cache::forget($key);
        $this->ban($ip, $type);
    }

    public function ban(string $ip, SecurityEventType $reason): SecurityIpBan {
        /** @var list<int> $steps */
        $steps = array_values(array_map('intval', (array) config('security.ip_ban.steps_minutes', [15, 60, 1440])));
        $level = SecurityIpBan::query()->where('ip', $ip)->where('created_at', '>=', now()->subDays(7))->count();
        $minutes = $steps[min($level, count($steps) - 1)] ?? 15;
        $until = CarbonImmutable::now()->addMinutes($minutes);

        $ban = SecurityIpBan::query()->create(['ip' => $ip, 'level' => $level + 1, 'reason' => $reason->value, 'banned_until' => $until]);
        Cache::put('sec:ban:' . $ip, $until->getTimestamp(), $until);
        app(SecurityEventLogger::class)->log(SecurityEventType::IpBanned, ['ip' => $ip, 'minutes' => $minutes, 'reason' => $reason->value]);

        return $ban;
    }

    public function bannedUntil(string $ip): ?CarbonImmutable {
        if (! $this->enabled()) {
            return null;
        }
        $timestamp = Cache::get('sec:ban:' . $ip);

        return is_numeric($timestamp) && (int) $timestamp > time() ? CarbonImmutable::createFromTimestamp((int) $timestamp) : null;
    }

    public function release(SecurityIpBan $ban, User $actor): void {
        $ban->forceFill(['released_at' => now(), 'released_by' => $actor->id])->save();
        Cache::forget('sec:ban:' . $ban->ip);
        $actor->audit('security.ipBanReleased', ['ip' => $ban->ip, 'level' => $ban->level]);
    }

    public function allowlisted(string $ip): bool {
        if (IPHelper::isPrivateIP($ip) || IPHelper::isLoopback($ip)) {
            return true;
        }
        $entries = array_merge(
            array_filter(array_map('trim', explode(',', (string) config('security.ip_ban.trusted_proxies', '')))),
            array_filter(array_map('trim', (array) config('security.ip_ban.allowlist', []))),
        );
        foreach ($entries as $entry) {
            if ($entry === $ip || (str_contains($entry, '/') && IPHelper::isInRange($ip, $entry))) {
                return true;
            }
        }

        return false;
    }
}
