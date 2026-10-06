{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('sessions.title.index') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('sessions.title.index'))
@include('partials.page-fill')

@php
    /** @var array<string, mixed> $overview */
    /** @var \Illuminate\Pagination\LengthAwarePaginator<int, array<string, mixed>> $users */
    $users = $overview['users'];
    $totals = $overview['totals'] ?? ['users' => 0, 'sessions' => 0, 'online' => 0, 'tokens' => 0];
    $available = ($overview['available'] ?? false) === true;
    $terminals = $overview['terminals'] ?? [];
    $remoteSupport = $overview['remote_support'] ?? [];
    $offlineTerminals = count(array_filter($terminals, static fn(array $t): bool => $t['active'] && ! $t['is_online']));
    $canRevoke = auth()->user()?->can(\App\Enums\User\Permission::SecuritySessionsRevoke->value) ?? false;
    $colspan = $canRevoke ? 5 : 4;
    $fmtDate = static fn($dt) => $dt instanceof \Carbon\CarbonInterface ? $dt->translatedFormat('d.m.Y H:i') : '—';
    $fmtAgo = static fn($dt) => $dt instanceof \Carbon\CarbonInterface ? $dt->diffForHumans() : '—';
@endphp

@section('content')
<x-index-page
    overflow="clip"
    :subtitle="__('sessions.subtitle')"
    :badge="__('sessions.stat.online') . ': ' . (int) $totals['online']"
    badge-tone="success"
>
    {{-- Datenschutzhinweis: nur Metadaten, nie Session-Payload/Token-Hash. --}}
    <div class="alert shrink-0 bg-info/10 border-info/30 text-sm text-base-content" role="note">
        <x-icon name="lock" />
        <span>{{ __('sessions.privacy_notice') }}</span>
    </div>

    @unless ($available)
        {{-- Ohne database-Treiber gibt es keine auflistbaren Sitzungen. --}}
        <div class="alert alert-warning shrink-0 bg-warning/10 border-warning/30 text-sm" role="note">
            <x-icon name="warning" />
            <span>{{ __('sessions.hint.driver', ['driver' => $overview['driver'] ?? config('session.driver')]) }}</span>
        </div>
    @endunless

    {{-- ── Kennzahlen über alle Mitglieder (live via Polling aktualisiert) ──── --}}
    <div class="grid shrink-0 grid-cols-2 gap-4 md:grid-cols-4">
        @foreach ([
            ['key' => 'users', 'icon' => 'group', 'label' => __('sessions.stat.users'), 'value' => (int) $totals['users']],
            ['key' => 'online', 'icon' => 'bolt', 'label' => __('sessions.stat.online'), 'value' => (int) $totals['online']],
            ['key' => 'sessions', 'icon' => 'devices', 'label' => __('sessions.stat.sessions'), 'value' => (int) $totals['sessions']],
            ['key' => 'tokens', 'icon' => 'key', 'label' => __('sessions.stat.tokens'), 'value' => (int) $totals['tokens']],
        ] as $tile)
            <x-card as="article" class="flex flex-col gap-1">
                <header class="flex items-center gap-2 text-muted">
                    <x-icon :name="$tile['icon']" />
                    <span class="text-xs">{{ $tile['label'] }}</span>
                </header>
                <p class="font-['Space_Grotesk'] text-2xl font-semibold" data-session-stat="{{ $tile['key'] }}">{{ $tile['value'] }}</p>
            </x-card>
        @endforeach
    </div>

    {{-- Änderungshinweis: erscheint, wenn das Polling neue Zahlen meldet. --}}
    <div id="sessions-stale-banner" class="alert alert-info shrink-0 bg-info/10 border-info/30 text-sm hidden" role="status">
        <x-icon name="sync" />
        <span>{{ __('sessions.live.changed') }}</span>
        <x-button tone="ghost" size="xs" id="sessions-reload-btn">{{ __('sessions.live.reload') }}</x-button>
    </div>

    {{-- Geräte ohne Nutzer-Login und die Fernwartungs-Historie stehen eingeklappt über
         der Nutzerliste — darunter lägen sie unter der Voll-Höhe-Tabelle. --}}
    @if ($terminals !== [] || $remoteSupport !== [])
        <div class="grid shrink-0 items-start gap-4 lg:grid-cols-2">
            @if ($terminals !== [])
                <details class="rounded-box border border-base-300 bg-base-200/50">
                    <summary class="cursor-pointer px-4 py-2 text-sm font-semibold">
                        <span class="inline-flex flex-wrap items-center gap-2 align-middle">
                            <x-icon name="point_of_sale" />
                            {{ __('sessions.section.terminals') }}
                            <span class="font-normal text-muted">({{ count($terminals) }})</span>
                            @if ($offlineTerminals > 0)
                                <x-status-badge tone="warning" size="xs">{{ __('sessions.terminal.offline') }}: {{ $offlineTerminals }}</x-status-badge>
                            @endif
                        </span>
                    </summary>
                    <div class="px-4 pb-4">
                        <p class="mb-2 text-xs text-muted">{{ __('sessions.hint.terminals') }}</p>
                        <x-table bare>
                            <x-slot:head>
                                <tr>
                                    <th>{{ __('sessions.col.terminal') }}</th>
                                    <th>{{ __('sessions.col.status') }}</th>
                                    <th>{{ __('sessions.col.last_seen') }}</th>
                                    @if ($canRevoke)
                                        <th class="text-right">{{ __('sessions.col.action') }}</th>
                                    @endif
                                </tr>
                            </x-slot:head>
                            @foreach ($terminals as $term)
                                <tr>
                                    <td class="font-medium">{{ $term['name'] }}</td>
                                    <td>
                                        @if (! $term['active'])
                                            <x-status-badge>{{ __('sessions.terminal.inactive') }}</x-status-badge>
                                        @elseif ($term['is_online'])
                                            <x-status-badge tone="success">{{ __('sessions.badge.online') }}</x-status-badge>
                                        @else
                                            <x-status-badge tone="warning">{{ __('sessions.terminal.offline') }}</x-status-badge>
                                        @endif
                                    </td>
                                    <td class="text-xs">{{ $term['last_seen_at'] ? $fmtAgo($term['last_seen_at']) : '—' }}</td>
                                    @if ($canRevoke)
                                        <td class="text-right">
                                            @if ($term['active'])
                                                <x-action-form :action="route('admin.sessions.terminals.deactivate', ['terminalSqid' => $term['sqid']])"
                                                      method="DELETE"
                                                      :confirm="__('sessions.confirm.deactivate_terminal', ['name' => $term['name']])"
                                                      confirm-icon="power_settings_new"
                                                      confirm-tone="error"
                                                      :confirm-label="__('sessions.action.deactivate_terminal')">
                                                    <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('sessions.action.deactivate_terminal') }}</x-button>
                                                </x-action-form>
                                            @else
                                                <span class="text-xs italic text-muted">{{ __('sessions.terminal.inactive') }}</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </x-table>
                    </div>
                </details>
            @endif

            {{-- Fernwartungen: read-only Historie, aus workDiary nicht beendbar. --}}
            @if ($remoteSupport !== [])
                <details class="rounded-box border border-base-300 bg-base-200/50">
                    <summary class="cursor-pointer px-4 py-2 text-sm font-semibold">
                        <span class="inline-flex flex-wrap items-center gap-2 align-middle">
                            <x-icon name="support_agent" />
                            {{ __('sessions.section.remote_support') }}
                            <span class="font-normal text-muted">({{ count($remoteSupport) }})</span>
                        </span>
                    </summary>
                    <div class="px-4 pb-4">
                        <p class="mb-2 text-xs text-muted">{{ __('sessions.hint.remote_support') }}</p>
                        <x-table bare>
                            <x-slot:head>
                                <tr>
                                    <th>{{ __('sessions.col.provider') }}</th>
                                    <th>{{ __('sessions.col.remote') }}</th>
                                    <th>{{ __('sessions.col.started') }}</th>
                                    <th>{{ __('sessions.col.ended') }}</th>
                                </tr>
                            </x-slot:head>
                            @foreach ($remoteSupport as $rs)
                                <tr>
                                    <td class="font-medium capitalize">{{ $rs['provider'] }}</td>
                                    <td class="text-xs">{{ $rs['label'] }}</td>
                                    <td class="text-xs">{{ $fmtDate($rs['started_at']) }}</td>
                                    <td class="text-xs">{{ $fmtDate($rs['ended_at']) }}</td>
                                </tr>
                            @endforeach
                        </x-table>
                    </div>
                </details>
            @endif
        </div>
    @endif

    {{-- ── Je Nutzer eine Kopfzeile, darunter Sitzungen, Tokens und Geräte ─── --}}
    <x-table scroll="flex" :pinRows="true" :zebra="false"
             empty-icon="devices_off" :empty-title="__('sessions.empty.title')" :empty-message="__('sessions.empty.description')">
        <x-slot:head>
            <tr>
                <th>{{ __('sessions.col.kind') }}</th>
                <th>{{ __('sessions.col.name') }}</th>
                <th>{{ __('sessions.col.ip') }}</th>
                <th>{{ __('sessions.col.last_activity') }}</th>
                @if ($canRevoke)
                    <th class="text-right">{{ __('sessions.col.action') }}</th>
                @endif
            </tr>
        </x-slot:head>
        @foreach ($users as $u)
            <tr class="bg-base-200/60">
                <td colspan="{{ $colspan }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-['Space_Grotesk'] font-semibold">{{ $u['name'] }}</span>
                        @if ($u['is_online'])
                            <x-status-badge tone="success" class="gap-1"><x-icon name="bolt" class="text-xs" />{{ __('sessions.badge.online') }}</x-status-badge>
                        @endif
                        <span class="text-xs text-muted">{{ $u['email'] }}</span>
                        <div class="ml-auto flex items-center gap-3 text-xs text-muted">
                            <span title="{{ __('sessions.last_login') }}">
                                <x-icon name="login" class="text-sm" />
                                {{ $u['last_login_at'] ? $fmtAgo($u['last_login_at']) : '—' }}
                            </span>
                            @if ($canRevoke && ($u['session_count'] > 0 || $u['token_count'] > 0))
                                <x-action-form :action="route('admin.sessions.user.destroy', ['userSqid' => $u['sqid']])"
                                      method="DELETE"
                                      :confirm="__('sessions.confirm.revoke_all', ['name' => $u['name']])"
                                      confirm-icon="logout"
                                      confirm-tone="error"
                                      :confirm-label="__('sessions.action.revoke_all')">
                                    <x-button type="submit" tone="ghost" size="xs" class="text-error">
                                        <x-icon name="logout" class="text-sm" />{{ __('sessions.action.revoke_all') }}
                                    </x-button>
                                </x-action-form>
                            @endif
                            {{-- MVP-1008: bestätigte Übernahme — Passwort und Passkeys ungültig, Reset-Link an den Nutzer. --}}
                            @if ($canRevoke && $u['user_id'] !== auth()->id())
                                <x-action-form :action="route('admin.sessions.user.secure', ['userSqid' => $u['sqid']])"
                                      :confirm="__('security.account_secure.confirm', ['name' => $u['name']])"
                                      confirm-icon="shield_lock"
                                      confirm-tone="error"
                                      :confirm-label="__('security.account_secure.action')">
                                    <x-button type="submit" tone="ghost" size="xs" class="text-error">
                                        <x-icon name="shield_lock" class="text-sm" />{{ __('security.account_secure.action') }}
                                    </x-button>
                                </x-action-form>
                            @endif
                        </div>
                    </div>
                </td>
            </tr>

            {{-- Web-/App-Sitzungen --}}
            @foreach ($u['sessions'] as $s)
                <tr @class(['hover', 'bg-success/5' => $s['is_online']])>
                    <td class="whitespace-nowrap text-muted">
                        <span class="flex items-center gap-1">
                            <x-icon :name="match ($s['device_type']) { 'mobile' => 'smartphone', 'tablet' => 'tablet', 'bot' => 'smart_toy', default => 'computer' }" class="text-sm" />
                            {{ __('sessions.kind.session') }}
                        </span>
                    </td>
                    <td class="max-w-xs">
                        <span class="text-sm" title="{{ $s['user_agent'] }}">{{ $s['device_label'] }}</span>
                        @if ($s['is_current'])
                            <x-status-badge tone="plain" size="xs" outline class="ml-1">{{ __('sessions.badge.this_device') }}</x-status-badge>
                        @elseif ($s['is_online'])
                            <x-status-badge tone="success" size="xs" class="ml-1">{{ __('sessions.badge.online') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="font-mono text-xs">
                        {{ $s['ip'] ?? '—' }}
                        @if (! empty($s['location']))
                            <span class="block font-sans text-muted">{{ $s['location'] }}</span>
                        @endif
                    </td>
                    <td class="text-xs" title="{{ $fmtDate($s['last_activity']) }}">{{ $fmtAgo($s['last_activity']) }}</td>
                    @if ($canRevoke)
                        <td class="text-right">
                            @if ($s['is_current'])
                                <span class="text-xs italic text-muted">{{ __('sessions.badge.this_device') }}</span>
                            @else
                                <x-action-form :action="route('admin.sessions.destroy', ['id' => $s['handle']])"
                                      method="DELETE"
                                      :confirm="__('sessions.confirm.revoke_session')"
                                      confirm-icon="logout"
                                      confirm-tone="error"
                                      :confirm-label="__('sessions.action.revoke_session')">
                                    <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('sessions.action.revoke_session') }}</x-button>
                                </x-action-form>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach

            {{-- API-Tokens --}}
            @foreach ($u['tokens'] as $t)
                <tr class="hover">
                    <td class="whitespace-nowrap text-muted">
                        <span class="flex items-center gap-1"><x-icon name="key" class="text-sm" />{{ __('sessions.kind.token') }}</span>
                    </td>
                    <td>
                        <span class="font-medium">{{ $t['name'] }}</span>
                        <span class="block text-xs text-muted">{{ __('sessions.col.created') }}: {{ $fmtDate($t['created_at']) }}</span>
                    </td>
                    <td class="text-xs text-muted">—</td>
                    <td class="text-xs">{{ $t['last_used_at'] ? $fmtAgo($t['last_used_at']) : '—' }}</td>
                    @if ($canRevoke)
                        <td class="text-right">
                            <x-action-form :action="route('admin.sessions.tokens.destroy', ['tokenSqid' => $t['sqid']])"
                                  method="DELETE"
                                  :confirm="__('sessions.confirm.revoke_token')"
                                  confirm-icon="key_off"
                                  confirm-tone="error"
                                  :confirm-label="__('sessions.action.revoke_token')">
                                <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('sessions.action.revoke_token') }}</x-button>
                            </x-action-form>
                        </td>
                    @endif
                </tr>
            @endforeach

            {{-- Standort-Erfassungsgeräte --}}
            @foreach ($u['location_devices'] as $d)
                <tr class="hover">
                    <td class="whitespace-nowrap text-muted">
                        <span class="flex items-center gap-1"><x-icon name="location_on" class="text-sm" />{{ __('sessions.kind.device') }}</span>
                    </td>
                    <td class="font-medium">{{ $d['label'] }}</td>
                    <td class="text-xs text-muted">—</td>
                    <td class="text-xs">{{ $d['last_used_at'] ? $fmtAgo($d['last_used_at']) : '—' }}</td>
                    @if ($canRevoke)
                        <td class="text-right">
                            <x-action-form :action="route('admin.sessions.devices.destroy', ['deviceSqid' => $d['sqid']])"
                                  method="DELETE"
                                  :confirm="__('sessions.confirm.revoke_device')"
                                  confirm-icon="link_off"
                                  confirm-tone="error"
                                  :confirm-label="__('sessions.action.revoke_device')">
                                <x-button type="submit" tone="ghost" size="xs" class="text-error">{{ __('sessions.action.revoke_device') }}</x-button>
                            </x-action-form>
                        </td>
                    @endif
                </tr>
            @endforeach
        @endforeach
    </x-table>

    <x-pagination :paginator="$users" standing />
</x-index-page>

{{-- Live-Refresh: pollt nur die Kennzahlen (keine PII) und blendet bei
     Änderungen einen Neuladen-Hinweis ein. Nonce'd wegen aktiver CSP. --}}
<script @cspNonce>
    (function () {
        var endpoint = @json(route('admin.sessions.data'));
        var initial = @json($totals);
        var intervalMs = 20000;
        var banner = document.getElementById('sessions-stale-banner');
        var reloadBtn = document.getElementById('sessions-reload-btn');

        if (reloadBtn) {
            reloadBtn.addEventListener('click', function () { window.location.reload(); });
        }

        function apply(totals) {
            var changed = false;
            ['users', 'online', 'sessions', 'tokens'].forEach(function (key) {
                var el = document.querySelector('[data-session-stat="' + key + '"]');
                if (el && typeof totals[key] !== 'undefined') {
                    el.textContent = totals[key];
                }
                if (initial[key] !== totals[key]) {
                    changed = true;
                }
            });
            if (changed && banner) {
                banner.classList.remove('hidden');
            }
        }

        function poll() {
            fetch(endpoint, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) { if (data && data.totals) { apply(data.totals); } })
                .catch(function () { /* Netzfehler ignorieren, nächster Tick versucht es erneut. */ });
        }

        window.setInterval(poll, intervalMs);
    })();
</script>
@endsection
