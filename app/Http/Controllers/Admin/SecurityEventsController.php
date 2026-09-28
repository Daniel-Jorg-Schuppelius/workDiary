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
use App\Models\Auth\SecurityEvent;
use App\Models\Platform\User;
use App\Services\Security\AccountTakeoverResponder;
use Illuminate\Http\{RedirectResponse, Request};
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
                'ip' => $row->ip?->getValue() ?? '',
                'count' => (int) $row->getAttribute('cnt'),
            ]);

        /** @var list<array{key: string, event: string, scope: string, window_minutes: int, limit: int}> $rules */
        $rules = (array) config('security.events.thresholds', []);
        $alarms = [];
        foreach ($rules as $rule) {
            $alarms[] = $rule + ['active' => (bool) Cache::get('security:alarm:' . $rule['key'], false)];
        }

        $events = SecurityEvent::query()
            ->with(['user' => fn ($query) => $query->withoutGlobalScopes()->select(['id', 'name'])])
            ->latest('occurred_at')->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.security-events.index', [
            'ipBans' => \App\Models\Auth\SecurityIpBan::query()->active()->orderBy('banned_until')->get(),
            'ipBanEnabled' => app(\App\Services\Security\IpBanService::class)->enabled(),
            'counts' => $counts,
            'topIps' => $topIps,
            'alarms' => $alarms,
            'events' => $events,
        ]);
    }

    /** Kontoübernahme zu einem Ereignis bestätigen und das Konto sichern (MVP-1008). */
    public function secureAccount(Request $request, SecurityEvent $event, AccountTakeoverResponder $responder): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->isGlobalAdmin(), 403);
        $target = $event->user_id !== null ? User::query()->withoutGlobalScopes()->find($event->user_id) : null;
        abort_unless($target instanceof User, 404);
        $responder->secure($target, $actor, AccountTakeoverResponder::SOURCE_SECURITY_EVENT, $event);

        return back()->with('success', __('security.account_secure.flash.done', ['name' => $target->name]));
    }

    /** Temporäre IP-Sperre vorzeitig aufheben (MVP-450, auditiert). */
    public function releaseIpBan(Request $request, \App\Models\Auth\SecurityIpBan $ban): \Illuminate\Http\RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof \App\Models\Platform\User && $user->isGlobalAdmin(), 403);
        app(\App\Services\Security\IpBanService::class)->release($ban, $user);

        return back()->with('success', __('IP-Adresse entsperrt.'));
    }
}
