@extends('layouts.app')

@section('title', __('security.title.index') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('security.title.index'))

@php
    /** @var array<string, mixed> $security */
    $sessions = $security['sessions'] ?? ['available' => false];
    $tokens = $security['tokens'] ?? ['available' => false, 'count' => 0, 'recent' => []];
    $integrations = $security['integrations'] ?? ['count' => 0, 'plugins' => [], 'references' => 0];
    $exports = $security['exports'] ?? ['recent' => []];
    $supportAccess = $security['support_access'] ?? ['count' => 0, 'recent' => []];
    $twoFactor = $security['two_factor'] ?? ['users_total' => 0, 'users_with_2fa' => 0, 'credentials' => 0];
    $encryption = $security['encryption'] ?? ['fields' => [], 'command' => 'security:encrypt-existing'];
    $fmt = static fn($dt) => $dt instanceof \Carbon\CarbonInterface ? $dt->format('Y-m-d H:i') : '—';
@endphp

@section('content')
<x-index-page
    :subtitle="__('security.subtitle')"
    :badge="__('security.scope.label') . ': ' . ($security['scope'] ?? __('security.scope.platform'))"
    badge-tone="info"
>
    {{-- Datenschutz-/Geheimnis-Hinweis: niemals Token-Werte/Secrets. --}}
    <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
        <x-icon name="lock" />
        <span>{{ __('security.privacy_notice') }}</span>
    </div>

    {{-- Folge-Hinweis: Lösch-/Aufbewahrungsläufe sind nicht Teil dieser Seite. --}}
    <div class="alert alert-warning bg-warning/10 border-warning/30 text-sm" role="note">
        <x-icon name="schedule" />
        <span>{{ __('security.deferred_notice') }}</span>
    </div>

    {{-- ── Kennzahlen-Karten ──────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        {{-- Sitzungen --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="devices" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('security.section.sessions') }}</h2>
                </header>
                @if (($sessions['available'] ?? false) === true)
                    <dl class="grid grid-cols-1 gap-1 text-sm">
                        <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                            <dt class="text-base-content/60">{{ __('security.field.sessions_total') }}</dt>
                            <dd class="font-mono text-xs">{{ $sessions['total'] ?? 0 }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-base-content/60">{{ __('security.field.sessions_active') }}</dt>
                            <dd class="font-mono text-xs">{{ $sessions['active'] ?? 0 }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm italic text-base-content/50">
                        {{ __('security.hint.sessions_driver', ['driver' => $sessions['driver'] ?? config('session.driver')]) }}
                    </p>
                @endif
            </div>
        </article>

        {{-- 2FA --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="encrypted" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('security.section.two_factor') }}</h2>
                </header>
                <dl class="grid grid-cols-1 gap-1 text-sm">
                    <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                        <dt class="text-base-content/60">{{ __('security.field.users_total') }}</dt>
                        <dd class="font-mono text-xs">{{ $twoFactor['users_total'] ?? 0 }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                        <dt class="text-base-content/60">{{ __('security.field.users_with_2fa') }}</dt>
                        <dd class="font-mono text-xs">{{ $twoFactor['users_with_2fa'] ?? 0 }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-2">
                        <dt class="text-base-content/60">{{ __('security.field.credentials') }}</dt>
                        <dd class="font-mono text-xs">{{ $twoFactor['credentials'] ?? 0 }}</dd>
                    </div>
                </dl>
                <p class="text-xs italic text-base-content/50">{{ __('security.hint.two_factor') }}</p>
            </div>
        </article>

        {{-- Integrationen --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="hub" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('security.section.integrations') }}</h2>
                </header>
                <dl class="grid grid-cols-1 gap-1 text-sm">
                    <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                        <dt class="text-base-content/60">{{ __('security.field.plugins_active') }}</dt>
                        <dd class="font-mono text-xs">{{ $integrations['count'] ?? 0 }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-2">
                        <dt class="text-base-content/60">{{ __('security.field.external_references') }}</dt>
                        <dd class="font-mono text-xs">{{ $integrations['references'] ?? 0 }}</dd>
                    </div>
                </dl>
                @if (! empty($integrations['plugins']))
                    <div class="flex flex-wrap gap-1">
                        @foreach ($integrations['plugins'] as $pluginId)
                            <span class="badge badge-outline badge-sm font-mono">{{ $pluginId }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm italic text-base-content/50">{{ __('security.empty.integrations') }}</p>
                @endif
                {{-- KI-Dienste (Feature 025): aktive Provider-Verbindungen, nie Schlüssel. --}}
                <dl class="grid grid-cols-1 gap-1 text-sm">
                    <div class="flex items-baseline justify-between gap-2 border-t border-base-200/70 pt-1">
                        <dt class="text-base-content/60">{{ __('ai.security.active_connections') }}</dt>
                        <dd class="font-mono text-xs">{{ $integrations['ai_count'] ?? 0 }}</dd>
                    </div>
                </dl>
                @if (! empty($integrations['ai_connections']))
                    <div class="flex flex-wrap gap-1">
                        @foreach ($integrations['ai_connections'] as $ai)
                            <span class="badge badge-outline badge-sm font-mono"
                                  title="{{ $ai['name'] }}">{{ $ai['provider'] }} ({{ $ai['local'] ? __('ai.field.local') : __('ai.field.cloud') }})</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </article>
    </div>

    {{-- ── API-Tokens ─────────────────────────────────────────────────── --}}
    <x-card :title="__('security.section.tokens')">
        <p class="mb-2 text-xs italic text-base-content/50">{{ __('security.hint.tokens_no_secret') }}</p>
        @if (! empty($tokens['recent']))
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('security.field.token_name') }}</th>
                            <th>{{ __('security.field.user') }}</th>
                            <th>{{ __('security.field.abilities') }}</th>
                            <th>{{ __('security.field.last_used_at') }}</th>
                            <th>{{ __('security.field.expires_at') }}</th>
                            <th>{{ __('security.field.created_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tokens['recent'] as $token)
                            <tr>
                                <td class="font-mono text-xs">{{ $token['name'] }}</td>
                                <td class="text-xs">{{ $token['user'] ?? '—' }}</td>
                                <td class="text-xs">
                                    @if (! empty($token['abilities']))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($token['abilities'] as $ability)
                                                <span class="badge badge-ghost badge-xs font-mono">{{ $ability }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="font-mono text-xs">{{ $fmt($token['last_used_at']) }}</td>
                                <td class="font-mono text-xs">{{ $fmt($token['expires_at']) }}</td>
                                <td class="font-mono text-xs">{{ $fmt($token['created_at']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty-state icon="key_off" :title="__('security.empty.tokens')" />
        @endif
    </x-card>

    {{-- ── Aktive Sitzungen (Detail) ──────────────────────────────────── --}}
    @if (($sessions['available'] ?? false) === true)
        <x-card :title="__('security.section.sessions')">
            @if (! empty($sessions['recent']))
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('security.field.user') }}</th>
                                <th>{{ __('security.field.ip') }}</th>
                                <th>{{ __('security.field.user_agent') }}</th>
                                <th>{{ __('security.field.last_activity') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessions['recent'] as $session)
                                <tr>
                                    <td class="text-xs">
                                        @if ($session['is_active'] ?? false)
                                            <span class="badge badge-success badge-xs mr-1">{{ __('security.status.active') }}</span>
                                        @endif
                                        {{ $session['user'] }}
                                    </td>
                                    <td class="font-mono text-xs">{{ $session['ip'] ?? '—' }}</td>
                                    <td class="text-xs text-base-content/60">{{ $session['user_agent'] ?? '—' }}</td>
                                    <td class="font-mono text-xs">{{ $fmt($session['last_activity']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-empty-state icon="devices_off" :title="__('security.empty.sessions')" />
            @endif
        </x-card>
    @endif

    {{-- ── Letzte Exporte ─────────────────────────────────────────────── --}}
    <x-card :title="__('security.section.exports')">
        @if (! empty($exports['recent']))
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('security.field.export_kind') }}</th>
                            <th>{{ __('security.field.export_subject') }}</th>
                            <th>{{ __('security.field.format') }}</th>
                            <th>{{ __('security.field.status') }}</th>
                            <th class="text-right">{{ __('security.field.rows') }}</th>
                            <th>{{ __('security.field.user') }}</th>
                            <th>{{ __('security.field.created_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($exports['recent'] as $export)
                            <tr>
                                <td class="text-xs">{{ $export['kind'] }}</td>
                                <td class="font-mono text-xs">{{ $export['subject'] ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ $export['format'] ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ $export['status'] ?? '—' }}</td>
                                <td class="text-right font-mono text-xs">{{ $export['rows'] ?? 0 }}</td>
                                <td class="text-xs">{{ $export['user'] ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ $fmt($export['created_at']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty-state icon="download_done" :title="__('security.empty.exports')" />
        @endif
    </x-card>

    {{-- ── Letzte Supportzugriffe ─────────────────────────────────────── --}}
    <x-card :title="__('security.section.support_access')">
        <p class="mb-2 text-xs italic text-base-content/50">{{ __('security.hint.support_access') }}</p>
        @if (! empty($supportAccess['recent']))
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('security.field.event') }}</th>
                            <th>{{ __('security.field.user') }}</th>
                            <th>{{ __('security.field.subject') }}</th>
                            <th>{{ __('security.field.ip') }}</th>
                            <th>{{ __('security.field.created_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($supportAccess['recent'] as $access)
                            <tr>
                                <td class="font-mono text-xs">{{ $access['event'] }}</td>
                                <td class="text-xs">{{ $access['user'] ?? '—' }}</td>
                                <td class="text-xs text-base-content/60">{{ $access['subject'] ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ $access['ip'] ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ $fmt($access['created_at']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty-state icon="support_agent" :title="__('security.empty.support_access')" />
        @endif
    </x-card>

    {{-- ── Sicherheitslage der Abhängigkeiten (OSV, Rang 70) ──────────── --}}
    <x-card :title="__('security.section.advisories')">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs italic text-base-content/50">
                {{ __('security.hint.advisories') }}
                @if ($advisoriesLastPull)
                    · {{ __('security.field.last_pull') }}: {{ \Illuminate\Support\Carbon::parse($advisoriesLastPull)->translatedFormat('d.m.Y H:i') }}
                @endif
            </p>
            <form method="POST" action="{{ route('admin.security.advisories.pull') }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-xs">
                    <x-icon name="refresh" class="text-sm" />
                    {{ __('security.action.pull_advisories') }}
                </button>
            </form>
        </div>
        @if ($advisories->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('security.field.severity') }}</th>
                            <th>{{ __('security.field.package') }}</th>
                            <th>{{ __('security.field.advisory') }}</th>
                            <th>{{ __('security.field.fixed_in') }}</th>
                            <th>{{ __('security.field.statement') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($advisories as $advisory)
                            <tr>
                                <td>
                                    @php
                                        $advisoryTone = match ($advisory->severity) {
                                            'critical' => 'badge-error',
                                            'high' => 'badge-error badge-outline',
                                            'medium' => 'badge-warning',
                                            'low' => 'badge-info',
                                            default => 'badge-ghost',
                                        };
                                    @endphp
                                    <span class="badge badge-sm {{ $advisoryTone }}">{{ $advisory->severity }}</span>
                                </td>
                                <td class="font-mono text-xs">{{ $advisory->package . '@' . $advisory->installed_version }}</td>
                                <td class="text-xs">
                                    <a href="https://osv.dev/vulnerability/{{ $advisory->external_id }}" target="_blank" rel="noopener noreferrer" class="link font-mono">{{ $advisory->external_id }}</a>
                                    @if ($advisory->summary)
                                        <div class="max-w-md truncate text-base-content/60">{{ $advisory->summary }}</div>
                                    @endif
                                </td>
                                <td class="font-mono text-xs">{{ $advisory->fixed_in ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.security.advisories.statement', $advisory) }}" class="flex items-center gap-1">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="statement" maxlength="1000"
                                               class="input input-bordered input-xs w-56"
                                               placeholder="{{ __('security.field.statement_placeholder') }}"
                                               value="{{ $advisory->statement }}">
                                        <button type="submit" class="btn btn-ghost btn-xs" title="{{ __('Speichern') }}">
                                            <x-icon name="save" class="text-sm" />
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty-state icon="verified_user" :title="__('security.empty.advisories')" />
        @endif
    </x-card>

    {{-- ── Verschlüsselung (at-rest) ──────────────────────────────────── --}}
    <x-card :title="__('security.section.encryption')">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            @if ($encryption['app_key_set'] ?? false)
                <span class="badge badge-success badge-sm">{{ __('security.status.app_key_set') }}</span>
            @else
                <span class="badge badge-error badge-sm">{{ __('security.status.app_key_missing') }}</span>
            @endif
            <code class="text-xs">php artisan {{ $encryption['command'] ?? 'security:encrypt-existing' }}</code>
        </div>
        <p class="mb-2 text-xs italic text-base-content/50">
            {{ __('security.hint.encryption', ['command' => $encryption['command'] ?? 'security:encrypt-existing']) }}
        </p>
        @if (! empty($encryption['fields']))
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('security.field.table') }}</th>
                            <th>{{ __('security.field.fields') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($encryption['fields'] as $table => $columns)
                            <tr>
                                <td class="font-mono text-xs">{{ $table }}</td>
                                <td class="text-xs">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($columns as $column)
                                            <span class="badge badge-ghost badge-xs font-mono">{{ $column }}</span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <p class="text-right text-xs text-base-content/40">
        {{ __('security.generated_at', ['at' => $fmt($security['generated_at'] ?? null)]) }}
    </p>
</x-index-page>
@endsection
