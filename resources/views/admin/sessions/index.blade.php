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

@php
    /** @var array<string, mixed> $overview */
    $users = $overview['users'] ?? [];
    $totals = $overview['totals'] ?? ['users' => 0, 'sessions' => 0, 'online' => 0, 'tokens' => 0];
    $available = ($overview['available'] ?? false) === true;
    $canRevoke = auth()->user()?->can(\App\Enums\User\Permission::SecuritySessionsRevoke->value) ?? false;
    $fmtDate = static fn($dt) => $dt instanceof \Carbon\CarbonInterface ? $dt->translatedFormat('d.m.Y H:i') : '—';
    $fmtAgo = static fn($dt) => $dt instanceof \Carbon\CarbonInterface ? $dt->diffForHumans() : '—';
@endphp

@section('content')
<x-index-page
    :subtitle="__('sessions.subtitle')"
    :badge="__('sessions.stat.online') . ': ' . (int) $totals['online']"
    badge-tone="success"
>
    {{-- Datenschutzhinweis: nur Metadaten, nie Session-Payload/Token-Hash. --}}
    <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
        <x-icon name="lock" />
        <span>{{ __('sessions.privacy_notice') }}</span>
    </div>

    @unless ($available)
        {{-- Ohne database-Treiber gibt es keine auflistbaren Sitzungen. --}}
        <div class="alert alert-warning bg-warning/10 border-warning/30 text-sm" role="note">
            <x-icon name="warning" />
            <span>{{ __('sessions.hint.driver', ['driver' => $overview['driver'] ?? config('session.driver')]) }}</span>
        </div>
    @endunless

    {{-- ── Kennzahlen (live via Polling aktualisiert) ─────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach ([
            ['key' => 'users', 'icon' => 'group', 'label' => __('sessions.stat.users'), 'value' => (int) $totals['users']],
            ['key' => 'online', 'icon' => 'bolt', 'label' => __('sessions.stat.online'), 'value' => (int) $totals['online']],
            ['key' => 'sessions', 'icon' => 'devices', 'label' => __('sessions.stat.sessions'), 'value' => (int) $totals['sessions']],
            ['key' => 'tokens', 'icon' => 'key', 'label' => __('sessions.stat.tokens'), 'value' => (int) $totals['tokens']],
        ] as $tile)
            <article class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-1 p-4">
                    <header class="flex items-center gap-2 text-muted">
                        <x-icon :name="$tile['icon']" />
                        <span class="text-xs">{{ $tile['label'] }}</span>
                    </header>
                    <p class="font-['Space_Grotesk'] text-2xl font-semibold" data-session-stat="{{ $tile['key'] }}">{{ $tile['value'] }}</p>
                </div>
            </article>
        @endforeach
    </div>

    {{-- Änderungshinweis: erscheint, wenn das Polling neue Zahlen meldet. --}}
    <div id="sessions-stale-banner" class="alert alert-info bg-info/10 border-info/30 text-sm hidden" role="status">
        <x-icon name="sync" />
        <span>{{ __('sessions.live.changed') }}</span>
        <button type="button" id="sessions-reload-btn" class="btn btn-xs btn-ghost">{{ __('sessions.live.reload') }}</button>
    </div>

    {{-- ── Je Nutzer ───────────────────────────────────────────────────── --}}
    @forelse ($users as $u)
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ $u['name'] }}</h2>
                        @if ($u['is_online'])
                            <span class="badge badge-success badge-sm gap-1"><x-icon name="bolt" class="text-xs" />{{ __('sessions.badge.online') }}</span>
                        @endif
                        <span class="text-xs text-muted">{{ $u['email'] }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-muted">
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
                    </div>
                </header>

                {{-- Web-/App-Sitzungen --}}
                @if (! empty($u['sessions']))
                    <div>
                        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">{{ __('sessions.section.sessions') }}</h3>
                        <x-table>
                            <x-slot:head>
                                    <tr>
                                        <th>{{ __('sessions.col.device') }}</th>
                                        <th>{{ __('sessions.col.ip') }}</th>
                                        <th>{{ __('sessions.col.last_activity') }}</th>
                                        @if ($canRevoke)
                                            <th class="text-right">{{ __('sessions.col.action') }}</th>
                                        @endif
                                    </tr>
                            </x-slot:head>
                                    @foreach ($u['sessions'] as $s)
                                        <tr @class(['bg-success/5' => $s['is_online']])>
                                            <td class="max-w-xs">
                                                <span class="flex items-center gap-1 text-sm" title="{{ $s['user_agent'] }}">
                                                    <x-icon :name="match ($s['device_type']) { 'mobile' => 'smartphone', 'tablet' => 'tablet', 'bot' => 'smart_toy', default => 'computer' }" class="text-sm text-muted" />
                                                    {{ $s['device_label'] }}
                                                </span>
                                                @if ($s['is_current'])
                                                    <span class="badge badge-outline badge-xs mt-1">{{ __('sessions.badge.this_device') }}</span>
                                                @elseif ($s['is_online'])
                                                    <span class="badge badge-success badge-xs mt-1">{{ __('sessions.badge.online') }}</span>
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
                                                        <x-action-form :action="route('admin.sessions.destroy', ['id' => $s['id']])"
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
                        </x-table>
                    </div>
                @endif

                {{-- API-Tokens --}}
                @if (! empty($u['tokens']))
                    <div>
                        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">{{ __('sessions.section.tokens') }}</h3>
                        <x-table>
                            <x-slot:head>
                                    <tr>
                                        <th>{{ __('sessions.col.name') }}</th>
                                        <th>{{ __('sessions.col.created') }}</th>
                                        <th>{{ __('sessions.col.last_used') }}</th>
                                        @if ($canRevoke)
                                            <th class="text-right">{{ __('sessions.col.action') }}</th>
                                        @endif
                                    </tr>
                            </x-slot:head>
                                    @foreach ($u['tokens'] as $t)
                                        <tr>
                                            <td class="font-medium">{{ $t['name'] }}</td>
                                            <td class="text-xs text-base-content/70">{{ $fmtDate($t['created_at']) }}</td>
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
                        </x-table>
                    </div>
                @endif

                {{-- Standort-Erfassungsgeräte --}}
                @if (! empty($u['location_devices']))
                    <div>
                        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">{{ __('sessions.section.devices') }}</h3>
                        <x-table>
                            <x-slot:head>
                                    <tr>
                                        <th>{{ __('sessions.col.name') }}</th>
                                        <th>{{ __('sessions.col.last_used') }}</th>
                                        @if ($canRevoke)
                                            <th class="text-right">{{ __('sessions.col.action') }}</th>
                                        @endif
                                    </tr>
                            </x-slot:head>
                                    @foreach ($u['location_devices'] as $d)
                                        <tr>
                                            <td class="font-medium">{{ $d['label'] }}</td>
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
                        </x-table>
                    </div>
                @endif
            </div>
        </article>
    @empty
        <x-empty-state
            icon="devices_off"
            :title="__('sessions.empty.title')"
            :description="__('sessions.empty.description')" />
    @endforelse

    {{-- ── Stempelterminals (org-weit, Geräte-Health · kein Nutzer-Login) ──── --}}
    @if (! empty($overview['terminals']))
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="point_of_sale" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('sessions.section.terminals') }}</h2>
                </header>
                <p class="text-xs text-muted">{{ __('sessions.hint.terminals') }}</p>
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
                            @foreach ($overview['terminals'] as $term)
                                <tr>
                                    <td class="font-medium">{{ $term['name'] }}</td>
                                    <td>
                                        @if (! $term['active'])
                                            <span class="badge badge-ghost badge-sm">{{ __('sessions.terminal.inactive') }}</span>
                                        @elseif ($term['is_online'])
                                            <span class="badge badge-success badge-sm">{{ __('sessions.badge.online') }}</span>
                                        @else
                                            <span class="badge badge-warning badge-sm">{{ __('sessions.terminal.offline') }}</span>
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
        </article>
    @endif

    {{-- ── Fernwartungen (read-only Historie · aus workDiary nicht beendbar) ── --}}
    @if (! empty($overview['remote_support']))
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="support_agent" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('sessions.section.remote_support') }}</h2>
                </header>
                <p class="text-xs text-muted">{{ __('sessions.hint.remote_support') }}</p>
                <x-table bare>
                    <x-slot:head>
                            <tr>
                                <th>{{ __('sessions.col.provider') }}</th>
                                <th>{{ __('sessions.col.remote') }}</th>
                                <th>{{ __('sessions.col.started') }}</th>
                                <th>{{ __('sessions.col.ended') }}</th>
                            </tr>
                    </x-slot:head>
                            @foreach ($overview['remote_support'] as $rs)
                                <tr>
                                    <td class="font-medium capitalize">{{ $rs['provider'] }}</td>
                                    <td class="text-xs">{{ $rs['label'] }}</td>
                                    <td class="text-xs">{{ $fmtDate($rs['started_at']) }}</td>
                                    <td class="text-xs">{{ $fmtDate($rs['ended_at']) }}</td>
                                </tr>
                            @endforeach
                </x-table>
            </div>
        </article>
    @endif
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
