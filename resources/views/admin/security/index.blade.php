{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
        <x-card as="article" class="flex flex-col gap-3">
            <header class="flex items-center gap-2">
                <x-icon name="devices" />
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('security.section.sessions') }}</h2>
            </header>
            @if (($sessions['available'] ?? false) === true)
                <x-detail-grid layout="split" divided>
                    <x-detail-grid.row :label="__('security.field.sessions_total')" class="font-mono text-xs">{{ $sessions['total'] ?? 0 }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('security.field.sessions_active')" class="font-mono text-xs">{{ $sessions['active'] ?? 0 }}</x-detail-grid.row>
                </x-detail-grid>
            @else
                <p class="text-sm italic text-muted">
                    {{ __('security.hint.sessions_driver', ['driver' => $sessions['driver'] ?? config('session.driver')]) }}
                </p>
            @endif
        </x-card>

        {{-- 2FA --}}
        <x-card as="article" class="flex flex-col gap-3">
            <header class="flex items-center gap-2">
                <x-icon name="encrypted" />
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('security.section.two_factor') }}</h2>
            </header>
            <x-detail-grid layout="split" divided>
                <x-detail-grid.row :label="__('security.field.users_total')" class="font-mono text-xs">{{ $twoFactor['users_total'] ?? 0 }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('security.field.users_with_2fa')" class="font-mono text-xs">{{ $twoFactor['users_with_2fa'] ?? 0 }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('security.field.credentials')" class="font-mono text-xs">{{ $twoFactor['credentials'] ?? 0 }}</x-detail-grid.row>
            </x-detail-grid>
            <p class="text-xs italic text-muted">{{ __('security.hint.two_factor') }}</p>
        </x-card>

        {{-- Integrationen --}}
        <x-card as="article" class="flex flex-col gap-3">
            <header class="flex items-center gap-2">
                <x-icon name="hub" />
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('security.section.integrations') }}</h2>
            </header>
            <x-detail-grid layout="split" divided>
                <x-detail-grid.row :label="__('security.field.plugins_active')" class="font-mono text-xs">{{ $integrations['count'] ?? 0 }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('security.field.external_references')" class="font-mono text-xs">{{ $integrations['references'] ?? 0 }}</x-detail-grid.row>
            </x-detail-grid>
            @if (! empty($integrations['plugins']))
                <div class="flex flex-wrap gap-1">
                    @foreach ($integrations['plugins'] as $pluginId)
                        <x-status-badge tone="plain" outline class="font-mono">{{ $pluginId }}</x-status-badge>
                    @endforeach
                </div>
            @else
                <p class="text-sm italic text-muted">{{ __('security.empty.integrations') }}</p>
            @endif
            {{-- KI-Dienste (Feature 025): aktive Provider-Verbindungen, nie Schlüssel. --}}
            <x-detail-grid layout="split" class="border-t border-base-200/70 pt-1">
                <x-detail-grid.row :label="__('ai.security.active_connections')" class="font-mono text-xs">{{ $integrations['ai_count'] ?? 0 }}</x-detail-grid.row>
            </x-detail-grid>
            @if (! empty($integrations['ai_connections']))
                <div class="flex flex-wrap gap-1">
                    @foreach ($integrations['ai_connections'] as $ai)
                        <x-status-badge tone="plain" outline class="font-mono" title="{{ $ai['name'] }}">{{ $ai['provider'] }} ({{ $ai['local'] ? __('ai.field.local') : __('ai.field.cloud') }})</x-status-badge>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>

    {{-- ── API-Tokens ─────────────────────────────────────────────────── --}}
    <x-card :title="__('security.section.tokens')">
        <p class="mb-2 text-xs italic text-muted">{{ __('security.hint.tokens_no_secret') }}</p>
        @if (! empty($tokens['recent']))
            <x-table bare>
                <x-slot:head>
                        <tr>
                            <th>{{ __('security.field.token_name') }}</th>
                            <th>{{ __('security.field.user') }}</th>
                            <th>{{ __('security.field.abilities') }}</th>
                            <th>{{ __('security.field.last_used_at') }}</th>
                            <th>{{ __('security.field.expires_at') }}</th>
                            <th>{{ __('security.field.created_at') }}</th>
                        </tr>
                </x-slot:head>
                        @foreach ($tokens['recent'] as $token)
                            <tr>
                                <td class="font-mono text-xs">{{ $token['name'] }}</td>
                                <td class="text-xs">{{ $token['user'] ?? '—' }}</td>
                                <td class="text-xs">
                                    @if (! empty($token['abilities']))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($token['abilities'] as $ability)
                                                <x-status-badge size="xs" class="font-mono">{{ $ability }}</x-status-badge>
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
            </x-table>
        @else
            <x-empty-state icon="key_off" :title="__('security.empty.tokens')" />
        @endif
    </x-card>

    {{-- ── Aktive Sitzungen (Detail) ──────────────────────────────────── --}}
    @if (($sessions['available'] ?? false) === true)
        <x-card :title="__('security.section.sessions')">
            @if (! empty($sessions['recent']))
                <x-table bare>
                    <x-slot:head>
                            <tr>
                                <th>{{ __('security.field.user') }}</th>
                                <th>{{ __('security.field.ip') }}</th>
                                <th>{{ __('security.field.user_agent') }}</th>
                                <th>{{ __('security.field.last_activity') }}</th>
                            </tr>
                    </x-slot:head>
                            @foreach ($sessions['recent'] as $session)
                                <tr>
                                    <td class="text-xs">
                                        @if ($session['is_active'] ?? false)
                                            <x-status-badge tone="success" size="xs" class="mr-1">{{ __('security.status.active') }}</x-status-badge>
                                        @endif
                                        {{ $session['user'] }}
                                    </td>
                                    <td class="font-mono text-xs">{{ $session['ip'] ?? '—' }}</td>
                                    <td class="text-xs text-muted">{{ $session['user_agent'] ?? '—' }}</td>
                                    <td class="font-mono text-xs">{{ $fmt($session['last_activity']) }}</td>
                                </tr>
                            @endforeach
                </x-table>
            @else
                <x-empty-state icon="devices_off" :title="__('security.empty.sessions')" />
            @endif
        </x-card>
    @endif

    {{-- ── Letzte Exporte ─────────────────────────────────────────────── --}}
    <x-card :title="__('security.section.exports')">
        @if (! empty($exports['recent']))
            <x-table bare>
                <x-slot:head>
                        <tr>
                            <th>{{ __('security.field.export_kind') }}</th>
                            <th>{{ __('security.field.export_subject') }}</th>
                            <th>{{ __('security.field.format') }}</th>
                            <th>{{ __('security.field.status') }}</th>
                            <th class="text-right">{{ __('security.field.rows') }}</th>
                            <th>{{ __('security.field.user') }}</th>
                            <th>{{ __('security.field.created_at') }}</th>
                        </tr>
                </x-slot:head>
                        @foreach ($exports['recent'] as $export)
                            <tr>
                                <td class="text-xs">{{ $export['kind'] }}</td>
                                <td class="font-mono text-xs">{{ $export['subject'] ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ $export['format'] ?? '—' }}</td>
                                <td class="text-xs">{{ $export['status_label'] ?? '—' }}</td>
                                <td class="text-right font-mono text-xs">{{ $export['rows'] ?? 0 }}</td>
                                <td class="text-xs">{{ $export['user'] ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ $fmt($export['created_at']) }}</td>
                            </tr>
                        @endforeach
            </x-table>
        @else
            <x-empty-state icon="download_done" :title="__('security.empty.exports')" />
        @endif
    </x-card>

    {{-- ── Letzte Supportzugriffe ─────────────────────────────────────── --}}
    <x-card :title="__('security.section.support_access')">
        <p class="mb-2 text-xs italic text-muted">{{ __('security.hint.support_access') }}</p>
        @if (! empty($supportAccess['recent']))
            <x-table bare>
                <x-slot:head>
                        <tr>
                            <th>{{ __('security.field.event') }}</th>
                            <th>{{ __('security.field.user') }}</th>
                            <th>{{ __('security.field.subject') }}</th>
                            <th>{{ __('security.field.ip') }}</th>
                            <th>{{ __('security.field.created_at') }}</th>
                        </tr>
                </x-slot:head>
                        @foreach ($supportAccess['recent'] as $access)
                            <tr>
                                <td class="font-mono text-xs">{{ $access['event'] }}</td>
                                <td class="text-xs">{{ $access['user'] ?? '—' }}</td>
                                <td class="text-xs text-muted">{{ $access['subject'] ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ $access['ip'] ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ $fmt($access['created_at']) }}</td>
                            </tr>
                        @endforeach
            </x-table>
        @else
            <x-empty-state icon="support_agent" :title="__('security.empty.support_access')" />
        @endif
    </x-card>

    {{-- ── Sicherheitslage der Abhängigkeiten (OSV, Rang 70) ──────────── --}}
    <x-card :title="__('security.section.advisories')">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs italic text-muted">
                {{ __('security.hint.advisories') }}
                @if ($advisoriesLastPull)
                    · {{ __('security.field.last_pull') }}: {{ \Illuminate\Support\Carbon::parse($advisoriesLastPull)->orgTz()->translatedFormat('d.m.Y H:i') }}
                @endif
            </p>
            @if ($isPlatformOperator)
                <form method="POST" action="{{ route('admin.security.advisories.pull') }}">
                    @csrf
                    <x-button type="submit" size="xs" icon="refresh" icon-size="0.875rem">
                        {{ __('security.action.pull_advisories') }}
                    </x-button>
                </form>
            @endif
        </div>
        @if ($advisories->isNotEmpty())
            <x-table bare>
                <x-slot:head>
                        <tr>
                            <th>{{ __('security.field.severity') }}</th>
                            <th>{{ __('security.field.package') }}</th>
                            <th>{{ __('security.field.advisory') }}</th>
                            <th>{{ __('security.field.fixed_in') }}</th>
                            <th>{{ __('security.field.statement') }}</th>
                        </tr>
                </x-slot:head>
                        @foreach ($advisories as $advisory)
                            <tr>
                                <td>
                                    @php
                                        $advisoryTone = match ($advisory->severity) {
                                            'critical', 'high' => 'error',
                                            'medium' => 'warning',
                                            'low' => 'info',
                                            default => 'ghost',
                                        };
                                    @endphp
                                    <x-status-badge :tone="$advisoryTone" :outline="$advisory->severity === 'high'">{{ $advisory->severity }}</x-status-badge>
                                </td>
                                <td class="font-mono text-xs">{{ $advisory->package . '@' . $advisory->installed_version }}</td>
                                <td class="text-xs">
                                    <a href="https://osv.dev/vulnerability/{{ $advisory->external_id }}" target="_blank" rel="noopener noreferrer" class="link font-mono">{{ $advisory->external_id }}</a>
                                    @if ($advisory->summary)
                                        <div class="max-w-md truncate text-muted">{{ $advisory->summary }}</div>
                                    @endif
                                </td>
                                <td class="font-mono text-xs">{{ $advisory->fixed_in ?? '—' }}</td>
                                <td>
                                    @if ($isPlatformOperator)
                                        <form method="POST" action="{{ route('admin.security.advisories.statement', $advisory) }}" class="flex items-center gap-1">
                                            @csrf
                                            @method('PUT')
                                            <input aria-label="{{ __('security.field.statement_placeholder') }}" type="text" name="statement" maxlength="1000"
                                                   class="input input-bordered input-xs w-56"
                                                   placeholder="{{ __('security.field.statement_placeholder') }}"
                                                   value="{{ $advisory->statement }}">
                                            <x-icon-btn icon="save" icon-size="0.875rem" type="submit" :label="__('Speichern')" />
                                        </form>
                                    @else
                                        <span class="text-xs">{{ $advisory->statement ?? '—' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
            </x-table>
        @else
            <x-empty-state icon="verified_user" :title="__('security.empty.advisories')" />
        @endif
    </x-card>

    {{-- ── Verschlüsselung (at-rest) ──────────────────────────────────── --}}
    <x-card :title="__('security.section.encryption')">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            @if ($encryption['app_key_set'] ?? false)
                <x-status-badge tone="success">{{ __('security.status.app_key_set') }}</x-status-badge>
            @else
                <x-status-badge tone="error">{{ __('security.status.app_key_missing') }}</x-status-badge>
            @endif
            <code class="text-xs">php artisan {{ $encryption['command'] ?? 'security:encrypt-existing' }}</code>
        </div>
        <p class="mb-2 text-xs italic text-muted">
            {{ __('security.hint.encryption', ['command' => $encryption['command'] ?? 'security:encrypt-existing']) }}
        </p>
        @if (! empty($encryption['fields']))
            <x-table bare>
                <x-slot:head>
                        <tr>
                            <th>{{ __('security.field.table') }}</th>
                            <th>{{ __('security.field.fields') }}</th>
                        </tr>
                </x-slot:head>
                        @foreach ($encryption['fields'] as $table => $columns)
                            <tr>
                                <td class="font-mono text-xs">{{ $table }}</td>
                                <td class="text-xs">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($columns as $column)
                                            <x-status-badge size="xs" class="font-mono">{{ $column }}</x-status-badge>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
            </x-table>
        @endif
    </x-card>

    <p class="text-right text-xs text-muted">
        {{ __('security.generated_at', ['at' => $fmt($security['generated_at'] ?? null)]) }}
    </p>
</x-index-page>
@endsection
