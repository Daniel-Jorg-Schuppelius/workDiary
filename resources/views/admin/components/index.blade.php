{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Geschützte Komponenten- und Versionsübersicht (Feature 044): App/Build,
  Laufzeitversionen, Module, Plugins und die letzte Release-SBOM
  (Kennzahlen + Download + synchrone Erzeugung). Nur Admin (metrics.view).
--}}

@extends('layouts.app')

@section('title', __('isms.components.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('isms.components.title'))

@section('content')
<x-index-page
    :subtitle="__('isms.components.subtitle')"
    :badge="__('isms.components.field.app_version') . ' ' . $appVersion"
    badge-tone="info"
>
    <div class="alert alert-info bg-info/10 border-info/30 text-sm" role="note">
        <x-icon name="privacy_tip" />
        <span>{{ __('isms.components.sbom_note') }}</span>
    </div>

    {{-- Health-Gate „nach Update" (Feature 022) --}}
    <article class="card border bg-base-100 shadow-sm {{ $health['healthy'] ? 'border-success/40' : 'border-error/40' }}">
        <div class="card-body gap-3">
            <header class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <x-icon name="monitor_heart" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('isms.components.health.title') }}</h2>
                </div>
                <x-status-badge :tone="$health['healthy'] ? 'success' : 'error'">
                    {{ $health['healthy'] ? __('isms.components.health.healthy') : __('isms.components.health.unhealthy', ['count' => $health['failed']]) }}
                </x-status-badge>
            </header>
            <p class="text-sm text-base-content/60">{{ __('isms.components.health.run_after_update') }}</p>
            <ul class="grid grid-cols-1 gap-1 text-sm md:grid-cols-2">
                @foreach ($health['checks'] as $check)
                    <li class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                        <span class="flex items-center gap-1">
                            <x-icon :name="$check['ok'] ? 'check_circle' : 'error'" class="{{ $check['ok'] ? 'text-success' : 'text-error' }}" />
                            {{ $check['name'] }}
                        </span>
                        <span class="text-right text-xs text-base-content/60">{{ $check['details'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </article>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        {{-- Anwendung --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="deployed_code" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('isms.components.section.app') }}</h2>
                </header>
                <dl class="grid grid-cols-1 gap-1 text-sm">
                    <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                        <dt class="text-base-content/60">{{ __('isms.components.field.app_version') }}</dt>
                        <dd class="font-mono text-xs">{{ $appVersion }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-2">
                        <dt class="text-base-content/60">{{ __('isms.components.field.build') }}</dt>
                        <dd class="font-mono text-xs">{{ $gitHash ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </article>

        {{-- Laufzeitumgebung --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="memory" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('isms.components.section.runtime') }}</h2>
                </header>
                <dl class="grid grid-cols-1 gap-1 text-sm">
                    <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                        <dt class="text-base-content/60">{{ __('isms.components.field.php') }}</dt>
                        <dd class="font-mono text-xs">{{ $phpVersion }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                        <dt class="text-base-content/60">{{ __('isms.components.field.laravel') }}</dt>
                        <dd class="font-mono text-xs">{{ $laravelVersion }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-2">
                        <dt class="text-base-content/60">{{ __('isms.components.field.database') }}</dt>
                        <dd class="font-mono text-xs">{{ $dbDriver }}{{ $dbVersion !== null ? ' (' . $dbVersion . ')' : '' }}</dd>
                    </div>
                </dl>
            </div>
        </article>

        {{-- Module --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="widgets" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('isms.components.section.modules') }}</h2>
                </header>
                <ul class="space-y-1 text-sm">
                    @foreach ($modules as $module)
                        <li class="flex items-center justify-between gap-2 border-b border-base-200/70 pb-1 last:border-b-0">
                            <span>{{ $module['label'] }} <span class="font-mono text-xs text-base-content/50">{{ $module['code'] }}</span></span>
                            <x-status-badge :tone="$module['enabled'] ? 'success' : 'ghost'" outline>
                                {{ $module['enabled'] ? __('isms.components.status.enabled') : __('isms.components.status.disabled') }}
                            </x-status-badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        </article>

        {{-- Plugins --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="extension" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('isms.components.section.plugins') }}</h2>
                </header>
                @if ($plugins === [])
                    <p class="text-sm italic text-base-content/50">—</p>
                @else
                    <ul class="space-y-1 text-sm">
                        @foreach ($plugins as $plugin)
                            <li class="flex items-center justify-between gap-2 border-b border-base-200/70 pb-1 last:border-b-0">
                                <span>{{ $plugin['name'] }} <span class="font-mono text-xs text-base-content/50">{{ $plugin['version'] }}</span></span>
                                <x-status-badge :tone="$plugin['enabled'] ? 'success' : 'ghost'" outline>
                                    {{ $plugin['enabled'] ? __('isms.components.status.enabled') : __('isms.components.status.disabled') }}
                                </x-status-badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </article>

        {{-- Release-SBOM --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm md:col-span-2">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="receipt_long" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('isms.components.section.sbom') }}</h2>
                </header>
                @if ($sbom !== null)
                    <dl class="grid grid-cols-1 gap-1 text-sm md:grid-cols-2">
                        <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                            <dt class="text-base-content/60">{{ __('isms.components.field.generated_at') }}</dt>
                            <dd class="font-mono text-xs">{{ $sbom['generated_at'] ?? '—' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                            <dt class="text-base-content/60">{{ __('isms.components.field.component_count') }}</dt>
                            <dd class="font-mono text-xs">{{ $sbom['total'] }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                            <dt class="text-base-content/60">{{ __('isms.components.field.composer_count') }}</dt>
                            <dd class="font-mono text-xs">{{ $sbom['composer'] }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                            <dt class="text-base-content/60">{{ __('isms.components.field.npm_count') }}</dt>
                            <dd class="font-mono text-xs">{{ $sbom['npm'] }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2 md:col-span-2">
                            <dt class="text-base-content/60">{{ __('isms.components.field.sha256') }}</dt>
                            <dd class="break-all font-mono text-xs">{{ $sbom['sha256'] }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm italic text-base-content/60">
                        {{ __('isms.components.sbom_missing', ['command' => 'php artisan sbom:generate']) }}
                    </p>
                @endif
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.components.sbom.generate') }}">
                        @csrf
                        <x-icon-btn icon="autorenew" tone="primary" size="sm" type="submit"
                                    show-label>{{ __('isms.components.action.generate') }}</x-icon-btn>
                    </form>
                    @if ($sbom !== null)
                        <x-icon-btn icon="download" tone="outline" size="sm"
                                    :href="route('admin.components.sbom.download')"
                                    show-label>{{ __('isms.components.action.download') }}</x-icon-btn>
                    @endif
                    {{-- CSAF-VEX für dieses Release (Nachtrag 044c). --}}
                    <form method="POST" action="{{ route('admin.components.vex') }}">
                        @csrf
                        <x-icon-btn icon="verified_user" tone="outline" size="sm" type="submit"
                                    show-label>{{ __('VEX erzeugen (CSAF)') }}</x-icon-btn>
                    </form>
                </div>
            </div>
        </article>

        {{-- Release-Manifest (Feature 022) --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm md:col-span-2">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="verified" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('isms.components.manifest.title') }}</h2>
                </header>
                <p class="text-sm text-base-content/60">{{ __('isms.components.manifest.note') }}</p>
                @if ($manifest !== null)
                    <dl class="grid grid-cols-1 gap-1 text-sm md:grid-cols-2">
                        <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                            <dt class="text-base-content/60">{{ __('isms.components.field.generated_at') }}</dt>
                            <dd class="font-mono text-xs">{{ $manifest['generated_at'] ?? '—' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                            <dt class="text-base-content/60">{{ __('isms.components.field.build') }}</dt>
                            <dd class="font-mono text-xs">{{ $manifest['build'] ?? '—' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                            <dt class="text-base-content/60">{{ __('isms.components.manifest.artifacts') }}</dt>
                            <dd class="font-mono text-xs">{{ $manifest['artifacts'] }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                            <dt class="text-base-content/60">{{ __('isms.components.manifest.signature') }}</dt>
                            <dd class="text-xs">
                                @if (! $manifest['signed'])
                                    <x-status-badge tone="ghost" size="sm">{{ __('isms.components.manifest.unsigned') }}</x-status-badge>
                                @elseif ($manifest['signature_valid'] === true)
                                    <x-status-badge tone="success" size="sm">{{ __('isms.components.manifest.signature_valid') }}</x-status-badge>
                                @else
                                    <x-status-badge tone="error" size="sm">{{ __('isms.components.manifest.signature_invalid') }}</x-status-badge>
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2 md:col-span-2">
                            <dt class="text-base-content/60">{{ __('isms.components.manifest.integrity') }}</dt>
                            <dd class="text-xs">
                                <x-status-badge :tone="$manifest['valid'] ? 'success' : 'error'" size="sm">
                                    {{ $manifest['valid'] ? __('isms.components.manifest.integrity_ok') : __('isms.components.manifest.integrity_broken') }}
                                </x-status-badge>
                            </dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm italic text-base-content/60">
                        {{ __('isms.components.manifest.missing', ['command' => 'php artisan release:manifest']) }}
                    </p>
                @endif
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.components.manifest.generate') }}">
                        @csrf
                        <x-icon-btn icon="autorenew" tone="primary" size="sm" type="submit"
                                    show-label>{{ __('isms.components.manifest.action_generate') }}</x-icon-btn>
                    </form>
                    @if ($manifest !== null)
                        <x-icon-btn icon="download" tone="outline" size="sm"
                                    :href="route('admin.components.manifest.download')"
                                    show-label>{{ __('isms.components.manifest.action_download') }}</x-icon-btn>
                    @endif
                </div>
            </div>
        </article>
    </div>

    {{-- Update-Verfügbarkeit (Feature 022, MVP-054): erkennen + erklären,
         KEIN Self-Update. Security-/Critical-Einstufungen sind hier immer
         sichtbar, auch wenn Routine-Meldungen stummgeschaltet sind. --}}
    <article class="mt-4 rounded-2xl border border-base-300 bg-base-100 p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-semibold">{{ __('updates.title.section') }}</h2>
                <p class="text-xs text-base-content/60">
                    {{ __('updates.field.mode') }}: <span class="font-mono">{{ $updatesMode }}</span>
                    @if ($updatesLastCheckedAt)
                        · {{ __('updates.field.last_checked') }}: {{ $updatesLastCheckedAt->format('d.m.Y H:i') }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($updatesMode !== 'disabled')
                    <form method="POST" action="{{ route('admin.components.updates.check') }}">
                        @csrf
                        <x-icon-btn icon="sync" tone="primary" size="sm" type="submit" show-label>{{ __('updates.action.check_now') }}</x-icon-btn>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.components.updates.import') }}" enctype="multipart/form-data" class="flex items-center gap-1">
                    @csrf
                    <input type="file" name="feed" accept=".json" required class="file-input file-input-bordered file-input-sm">
                    <x-icon-btn icon="upload_file" tone="outline" size="sm" type="submit" show-label>{{ __('updates.action.import') }}</x-icon-btn>
                </form>
            </div>
        </div>

        @if (count($updates) === 0)
            <p class="text-sm text-base-content/60">{{ __('updates.empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('updates.field.component') }}</th>
                            <th>{{ __('updates.field.versions') }}</th>
                            <th>{{ __('updates.field.classification') }}</th>
                            <th>{{ __('updates.field.requirements') }}</th>
                            <th class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($updates as $update)
                            <tr @class(['opacity-50' => $update->isMuted() && ! $update->isSecurityRelevant()])>
                                <td>
                                    <span class="font-medium">{{ $update->component_key }}</span>
                                    <span class="text-xs text-base-content/50">({{ $update->component_type }}, {{ $update->channel }})</span>
                                </td>
                                <td class="font-mono text-sm">{{ $update->installed_version }} → {{ $update->available_version }}</td>
                                <td>
                                    <x-status-badge size="xs" :tone="match($update->classification) { 'critical' => 'error', 'security' => 'error', 'recommended' => 'warning', default => 'info' }">
                                        {{ __('updates.classification.' . $update->classification) }}
                                    </x-status-badge>
                                    @unless ($update->compatible)
                                        <x-status-badge size="xs" tone="error">{{ __('updates.field.incompatible') }}</x-status-badge>
                                    @endunless
                                </td>
                                <td class="text-xs">
                                    @php $requires = (array) ($update->requires ?? []); @endphp
                                    <div class="flex flex-wrap gap-1">
                                        @if (($requires['backup'] ?? false))<x-status-badge size="xs" tone="warning">{{ __('updates.requires.backup') }}</x-status-badge>@endif
                                        @if (($requires['maintenance_window'] ?? false))<x-status-badge size="xs" tone="warning">{{ __('updates.requires.maintenance_window') }}</x-status-badge>@endif
                                        @if (($requires['migrations'] ?? false))<x-status-badge size="xs" tone="ghost">{{ __('updates.requires.migrations') }}</x-status-badge>@endif
                                        @if (is_string($requires['manual_steps'] ?? null))<span class="text-base-content/60">{{ $requires['manual_steps'] }}</span>@endif
                                        @if ($update->changelog_url)
                                            <a href="{{ $update->changelog_url }}" target="_blank" rel="noopener noreferrer" class="link link-hover">{{ __('updates.field.changelog') }}</a>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-right">
                                    @unless ($update->isSecurityRelevant())
                                        <div class="inline-flex items-center gap-1">
                                            <form method="POST" action="{{ route('admin.components.updates.snooze', $update) }}">
                                                @csrf
                                                <x-icon-btn icon="snooze" type="submit" :label="__('updates.action.snooze')" />
                                            </form>
                                            <form method="POST" action="{{ route('admin.components.updates.acknowledge', $update) }}">
                                                @csrf
                                                <x-icon-btn icon="notifications_off" type="submit" :label="__('updates.action.acknowledge')" />
                                            </form>
                                        </div>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>
</x-index-page>
@endsection
