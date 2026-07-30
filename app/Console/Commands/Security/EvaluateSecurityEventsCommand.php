<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EvaluateSecurityEventsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Security;

use App\Enums\Notification\NotificationEvent;
use App\Models\{SecurityEvent, User};
use App\Notifications\GenericEventNotification;
use App\Services\Security\SecurityCrisisEscalator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, Notification};

/**
 * Schwellwert-Auswertung der Security-Events (Feature 096, MVP-445):
 * Regeln aus config/security.php (Fenster + Limit je Eventklasse, Scope
 * global oder je IP). Alarm an Plattform-Admins NUR beim Zustandswechsel
 * (Regel neu gerissen → Alarm, beruhigt → Entwarnung) — Zustand im Cache.
 * Räumt zugleich die Retention ab (Pruning).
 */
class EvaluateSecurityEventsCommand extends Command {
    protected $signature = 'security:evaluate';

    protected $description = 'Wertet Security-Events gegen die Schwellwert-Regeln aus (Zustandswechsel-Alarme).';

    public function handle(): int {
        $this->prune();

        /** @var list<array{key: string, event: string, scope: string, window_minutes: int, limit: int, crisis?: bool}> $rules */
        $rules = (array) config('security.events.thresholds', []);
        foreach ($rules as $rule) {
            $this->evaluate($rule);
        }

        return self::SUCCESS;
    }

    /** @param array{key: string, event: string, scope: string, window_minutes: int, limit: int, crisis?: bool} $rule */
    private function evaluate(array $rule): void {
        $since = now()->subMinutes(max(1, (int) $rule['window_minutes']));
        $query = SecurityEvent::query()
            ->where('event', $rule['event'])
            ->where('occurred_at', '>=', $since);

        $offenderIp = null;
        if ($rule['scope'] === 'ip') {
            $top = $query->clone()
                ->whereNotNull('ip')
                ->selectRaw('ip, COUNT(*) as cnt')
                ->groupBy('ip')
                ->orderByDesc('cnt')
                ->first();
            $count = $top !== null ? (int) $top->getAttribute('cnt') : 0;
            $offenderIp = $top?->getAttribute('ip');
        } else {
            $count = $query->count();
        }

        $breached = $count >= max(1, (int) $rule['limit']);
        $cacheKey = 'security:alarm:' . $rule['key'];
        $active = (bool) Cache::get($cacheKey, false);

        // Massenangriff-Regeln (MVP-449) eskalieren als CrisisAlert statt als
        // normale Notification; Entwarnung läuft über dieselbe
        // Zustandswechsel-Logik.
        $isCrisis = (bool) ($rule['crisis'] ?? false);

        if ($breached && ! $active) {
            Cache::put($cacheKey, true, now()->addDays(7));
            if ($isCrisis) {
                app(SecurityCrisisEscalator::class)->raise($rule, $count);
            } else {
                $this->notify($rule, $count, is_string($offenderIp) ? $offenderIp : null, alarm: true);
            }
        } elseif (! $breached && $active) {
            Cache::forget($cacheKey);
            if ($isCrisis) {
                app(SecurityCrisisEscalator::class)->allClear($rule, $count);
            } else {
                $this->notify($rule, $count, null, alarm: false);
            }
        }
    }

    /** @param array{key: string, event: string, scope: string, window_minutes: int, limit: int} $rule */
    private function notify(array $rule, int $count, ?string $ip, bool $alarm): void {
        $admins = User::query()->where('is_platform_admin', true)->get();
        if ($admins->isEmpty()) {
            return;
        }

        $params = [
            'event' => $rule['event'],
            'count' => $count,
            'window' => (int) $rule['window_minutes'],
            'limit' => (int) $rule['limit'],
            'ip' => $ip ?? '—',
        ];
        $titleKey = $alarm ? 'notification.message.security_threat_title' : 'notification.message.security_calm_title';
        $messageKey = $alarm ? 'notification.message.security_threat_message' : 'notification.message.security_calm_message';

        Notification::send($admins, new GenericEventNotification(
            NotificationEvent::SecurityThreat,
            [
                'title' => (string) __($titleKey, $params),
                'title_key' => $titleKey,
                'title_params' => $params,
                'message' => (string) __($messageKey, $params),
                'message_key' => $messageKey,
                'message_params' => $params,
                'url' => route('admin.security-events.index'),
            ],
            ['database', 'mail'],
        ));
    }

    private function prune(): void {
        $days = max(1, (int) config('security.events.retention_days', 90));
        SecurityEvent::query()->where('occurred_at', '<', now()->subDays($days))->delete();
    }
}
