@extends('layouts.app')

@section('title', __('metrics.title.index') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('metrics.title.index'))

@php
    /** @var array<string, mixed> $metrics */
    $queue = $metrics['queue'] ?? ['available' => false];
    $backups = $metrics['backups'] ?? [];
    $pluginErrors = $metrics['plugin_errors'] ?? ['count' => 0, 'recent' => []];
    $storage = $metrics['storage'] ?? [];
    $moduleCounts = $metrics['module_counts'] ?? [];
    $featureUsage = $metrics['feature_usage'] ?? [];
    $telemetry = $metrics['telemetry'] ?? ['enabled' => true, 'counters' => []];
    $fmtBytes = static fn (int $bytes): string => \Illuminate\Support\Number::fileSize($bytes, precision: 1);
@endphp

@section('content')
<x-index-page
    :subtitle="__('metrics.subtitle')"
    :badge="__('metrics.field.version') . ' ' . ($metrics['version'] ?? '—')"
    badge-tone="info"
>
    {{-- Telemetrie-Hinweis: Daten bleiben lokal, kein externes Senden. --}}
    <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
        <x-icon name="privacy_tip" />
        <span>{{ __('metrics.privacy_notice') }}</span>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        {{-- Queue --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="queue" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('metrics.section.queue') }}</h2>
                </header>
                @if (($queue['available'] ?? false) === true)
                    <dl class="grid grid-cols-1 gap-1 text-sm">
                        <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1">
                            <dt class="text-base-content/60">{{ __('metrics.field.queue_pending') }}</dt>
                            <dd class="font-mono text-xs">{{ $queue['pending'] ?? '—' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-base-content/60">{{ __('metrics.field.queue_failed') }}</dt>
                            <dd class="font-mono text-xs">{{ $queue['failed'] ?? '—' }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm italic text-base-content/50">{{ __('metrics.empty.queue') }}</p>
                @endif
            </div>
        </article>

        {{-- Backups --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="backup" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('metrics.section.backups') }}</h2>
                </header>
                @if (count($backups) > 0)
                    <ul class="space-y-1 text-sm">
                        @foreach ($backups as $hb)
                            <li class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1 last:border-0">
                                <span class="text-base-content/70">{{ $hb['occurred_at']?->translatedFormat('d.m.Y H:i') ?? '—' }}</span>
                                <span class="font-mono text-xs text-base-content/60">
                                    {{ $hb['size_bytes'] !== null ? $fmtBytes((int) $hb['size_bytes']) : '—' }}
                                    @if (!empty($hb['source'])) · {{ $hb['source'] }} @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm italic text-base-content/50">{{ __('metrics.empty.backups') }}</p>
                @endif
            </div>
        </article>

        {{-- Plugin-Fehler (7 Tage) --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <x-icon name="bug_report" />
                        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('metrics.section.plugin_errors') }}</h2>
                    </div>
                    <span class="badge badge-outline {{ ($pluginErrors['count'] ?? 0) > 0 ? 'badge-warning' : 'badge-success' }}">{{ $pluginErrors['count'] ?? 0 }}</span>
                </header>
                @if (count($pluginErrors['recent'] ?? []) > 0)
                    <ul class="space-y-1 text-xs text-base-content/70">
                        @foreach ($pluginErrors['recent'] as $err)
                            <li class="border-b border-base-200/70 pb-1 last:border-0">
                                <span class="font-mono">{{ $err['plugin_id'] }}</span> ({{ $err['phase'] }})
                                — {{ \Illuminate\Support\Str::limit($err['message'], 80) }}
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm italic text-base-content/50">{{ __('metrics.empty.plugin_errors') }}</p>
                @endif
            </div>
        </article>

        {{-- Speicher (DB-Metadaten) --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="sd_storage" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('metrics.section.storage') }}</h2>
                </header>
                <dl class="grid grid-cols-1 gap-1 text-sm">
                    @foreach (['attachments' => __('metrics.field.attachments'), 'document_versions' => __('metrics.field.document_versions')] as $key => $label)
                        <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1 last:border-0">
                            <dt class="text-base-content/60">{{ $label }}</dt>
                            <dd class="font-mono text-xs">
                                {{ $storage[$key]['count'] ?? 0 }} · {{ $fmtBytes((int) ($storage[$key]['bytes'] ?? 0)) }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
                <p class="text-xs text-base-content/50">{{ __('metrics.hint.storage_db_metadata') }}</p>
            </div>
        </article>

        {{-- Aktive Benutzer --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="group" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('metrics.section.active_users') }}</h2>
                </header>
                @if (($metrics['active_users'] ?? null) !== null)
                    <p class="text-3xl font-semibold">{{ $metrics['active_users'] }}</p>
                    <p class="text-xs text-base-content/50">{{ __('metrics.hint.active_users') }}</p>
                @else
                    <p class="text-sm italic text-base-content/50">{{ __('metrics.empty.active_users') }}</p>
                @endif
            </div>
        </article>

        {{-- Datensätze je Kernmodul --}}
        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <header class="flex items-center gap-2">
                    <x-icon name="database" />
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('metrics.section.module_counts') }}</h2>
                </header>
                <dl class="grid grid-cols-1 gap-1 text-sm">
                    @foreach ($moduleCounts as $module => $count)
                        <div class="flex items-baseline justify-between gap-2 border-b border-base-200/70 pb-1 last:border-0">
                            <dt class="text-base-content/60">{{ __('metrics.module.' . $module) }}</dt>
                            <dd class="font-mono text-xs">{{ $count }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </article>
    </div>

    {{-- Feature-Nutzung (30 Tage, aggregiert) --}}
    <x-card :title="__('metrics.section.feature_usage')">
        @if (count($featureUsage) > 0)
            <x-table bare>
                <x-slot:head>
                        <tr>
                            <th>{{ __('metrics.field.feature') }}</th>
                            <th class="text-right">{{ __('metrics.field.usage_total') }}</th>
                            <th class="text-right">{{ __('metrics.field.last_used_on') }}</th>
                        </tr>
                </x-slot:head>
                        @foreach ($featureUsage as $row)
                            <tr>
                                <td class="font-mono text-xs">{{ $row['feature'] }}</td>
                                <td class="text-right font-mono text-xs">{{ $row['total'] }}</td>
                                <td class="text-right font-mono text-xs">{{ $row['last_used_on'] !== null ? \Illuminate\Support\Carbon::parse($row['last_used_on'])->translatedFormat('d.m.Y') : '—' }}</td>
                            </tr>
                        @endforeach
            </x-table>
        @else
            <x-empty-state
                icon='<span class="material-symbols-outlined" aria-hidden="true">monitoring</span>'
                :title="__('metrics.empty.feature_usage')" />
        @endif
        <p class="mt-2 text-xs text-base-content/50">{{ __('metrics.hint.feature_usage_window') }}</p>
    </x-card>

    {{-- Metrik-Transparenz (MVP-337): welche Zähler erhoben werden, wo sie
         liegen und wo der Telemetrie-Schalter sitzt. --}}
    <x-card :title="__('metrics.section.transparency')">
        <div class="flex flex-wrap items-center gap-3">
            <x-status-badge :tone="($telemetry['enabled'] ?? true) ? 'success' : 'warning'">
                {{ ($telemetry['enabled'] ?? true) ? __('metrics.transparency.status_enabled') : __('metrics.transparency.status_disabled') }}
            </x-status-badge>
            @can(\App\Enums\User\Permission::PlatformSettingsManage->value)
                <a class="link link-primary text-sm" href="{{ route('admin.settings.index', ['q' => 'telemetry.enabled']) }}">
                    {{ __('metrics.transparency.settings_link') }}
                </a>
            @endcan
        </div>
        <p class="mt-2 text-sm text-base-content/70">{{ __('metrics.transparency.intro') }}</p>
        <x-table class="mt-3">
            <x-slot:head>
                    <tr>
                        <th>{{ __('metrics.field.feature') }}</th>
                        <th>{{ __('metrics.field.counter_description') }}</th>
                    </tr>
            </x-slot:head>
                    @foreach (($telemetry['counters'] ?? []) as $counterKey)
                        <tr>
                            <td class="font-mono text-xs">{{ $counterKey }}</td>
                            <td class="text-sm">{{ __('metrics.counter.' . $counterKey) }}</td>
                        </tr>
                    @endforeach
        </x-table>
        <p class="mt-2 text-xs text-base-content/50">{{ __('metrics.transparency.storage') }}</p>
        <p class="text-xs text-base-content/50">{{ __('metrics.transparency.retention') }}</p>
    </x-card>

    <p class="text-xs text-base-content/40">
        {{ __('metrics.generated_at', ['at' => ($metrics['generated_at'] ?? now())->translatedFormat('d.m.Y H:i:s')]) }}
        · {{ __('metrics.field.version') }}: {{ $metrics['version'] ?? '—' }}
    </p>
</x-index-page>
@endsection
