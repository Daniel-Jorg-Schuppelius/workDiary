<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityEventsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Security-Dashboard (Feature 096, MVP-445) — nur Plattform-Admin:
 * Ereignis-Zähler, Top-IPs und Verlauf der persistierten Security-Events.
 * Hinweisgeber-Fehlversuche erscheinen hier bewusst NICht — sie werden nie
 * persistiert (Anonymitätsschutz HinSchG, nur rotierte Datei für fail2ban).
 */
class SecurityEventsController extends Controller {
    public function index(Request $request): View {
        abort_unless($request->user()?->isGlobalAdmin() === true, 403);

        $since = now()->subDay();

        $counts = SecurityEvent::query()
            ->where('occurred_at', '>=', $since)
            ->selectRaw('event, COUNT(*) as cnt')
            ->groupBy('event')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn(SecurityEvent $row): array => [
                'event' => (string) $row->getRawOriginal('event'),
                'count' => (int) $row->getAttribute('cnt'),
            ]);

        $topIps = SecurityEvent::query()
            ->where('occurred_at', '>=', $since)
            ->whereNotNull('ip')
            ->selectRaw('ip, COUNT(*) as cnt')
            ->groupBy('ip')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get()
            ->map(fn(SecurityEvent $row): array => [
                'ip' => (string) $row->ip,
                'count' => (int) $row->getAttribute('cnt'),
            ]);

        /** @var list<array{key: string, event: string, scope: string, window_minutes: int, limit: int}> $rules */
        $rules = (array) config('security.events.thresholds', []);
        $alarms = [];
        foreach ($rules as $rule) {
            $alarms[] = $rule + ['active' => (bool) Cache::get('security:alarm:' . $rule['key'], false)];
        }

        $events = SecurityEvent::query()
            ->latest('occurred_at')->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.security-events.index', [
            'counts' => $counts,
            'topIps' => $topIps,
            'alarms' => $alarms,
            'events' => $events,
        ]);
    }
}
