{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : growth.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('scope.growth.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('scope.growth.title'))

@php
    /** @var list<array{code: string, label: string, description: string, status: \App\Enums\Licensing\ModuleStatus, state: string, packages: list<string>, requires: list<string>, requires_missing: list<string>, workspaces: list<string>, chosen: bool}> $overview */
    // Zusatzpaket für Finanzformate: Verfügbarkeit hier geprüft, nicht im Plattformdienst (Modulgrenze).
    $financialFormatsMissing = ! \App\Services\Billing\FinancialFormatsSupport::isAvailable();
    /** @var \Closure(string): string $workspaceLabel */
    /** @var \Closure(string): string $moduleLabel */
    /** @var string $licenseUid */
    /** @var int|null $pendingVersion */
    /** @var array{source: ?string, key: ?string, version: int, mode: string} $profile */
    $byState = ['available' => [], 'not_licensed' => [], 'active' => [], 'blocked' => []];
    foreach ($overview as $row) {
        $bucket = match ($row['state']) {
            'disabled', 'growth' => 'available',
            'not_licensed' => 'not_licensed',
            'blocked' => 'blocked',
            default => 'active',
        };
        $byState[$bucket][] = $row;
    }
    $stateTone = ['active' => 'success', 'disabled' => 'neutral', 'growth' => 'info', 'not_licensed' => 'ghost', 'blocked' => 'warning'];
@endphp

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('scope.growth.subtitle')" :back="route('admin.scope.index')" :back-label="__('scope.title.index')">
                <x-slot:actions>
                    <x-button href="{{ route('admin.license.index') }}" tone="ghost" size="sm" icon="key">{{ __('Lizenz') }}</x-button>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        @if ($pendingVersion !== null && $profile['key'] !== null)
            <div role="status" class="alert alert-info rounded-2xl px-5 py-3 text-sm shadow-xs">
                <x-icon name="new_releases" class="text-base" />
                <span>{{ __('scope.version.banner', ['package' => $profile['key'], 'version' => $pendingVersion]) }}</span>
                <x-button href="{{ route('admin.scope.setup') }}" data-entry-modal-trigger tone="ghost" size="sm">{{ __('scope.version.review') }}</x-button>
            </div>
        @endif

        {{-- Enthalten, noch nicht aktiv --}}
        <x-card class="mt-4" padding="p-0">
            <div class="border-b border-base-300 px-4 py-3">
                <h2 class="text-sm font-semibold uppercase tracking-wider opacity-60">{{ __('scope.growth.section.available') }}</h2>
            </div>
            @if ($byState['available'] === [])
                <x-empty-state icon="check_circle" :title="__('scope.growth.none_available')" compact />
            @else
                <ul class="divide-y divide-base-300">
                    @foreach ($byState['available'] as $row)
                        <li class="flex flex-col gap-3 p-3 sm:flex-row sm:items-start">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold">{{ __($row['label']) }}</span>
                                    <x-status-badge :tone="$stateTone[$row['state']]">{{ __('scope.growth.state.' . $row['state']) }}</x-status-badge>
                                </div>
                                @if ($row['description'] !== '')
                                    <p class="mt-0.5 text-xs text-base-content/70">{{ __($row['description']) }}</p>
                                @endif
                                {{-- Vorschau: was die Aktivierung ändert --}}
                                <ul class="mt-1 space-y-0.5 text-xs text-muted">
                                    <li>{{ __('scope.growth.preview_nav', ['module' => __($row['label'])]) }}</li>
                                    @if ($row['workspaces'] !== [])
                                        <li>{{ __('scope.growth.workspaces', ['workspaces' => implode(', ', array_map($workspaceLabel, $row['workspaces']))]) }}</li>
                                    @endif
                                    @if ($row['requires'] !== [])
                                        <li class="{{ $row['requires_missing'] !== [] ? 'text-warning' : '' }}">
                                            {{ $row['requires_missing'] !== []
                                                ? __('scope.growth.requires_missing', ['modules' => implode(', ', array_map($moduleLabel, $row['requires_missing']))])
                                                : __('scope.growth.requires', ['modules' => implode(', ', array_map($moduleLabel, $row['requires']))]) }}
                                        </li>
                                    @endif
                                    @if ($financialFormatsMissing && in_array($row['code'], \App\Services\Licensing\ModuleScopeService::REQUIRES_FINANCIAL_FORMATS, true))
                                        <li class="text-warning">{{ __('scope.growth.needs_package') }}</li>
                                    @endif
                                </ul>
                            </div>
                            <form method="POST" action="{{ route('admin.scope.growth.activate') }}" class="shrink-0">
                                @csrf
                                <input type="hidden" name="module" value="{{ $row['code'] }}">
                                <x-button type="submit" tone="primary" size="sm" icon="power_settings_new">{{ __('scope.growth.activate') }}</x-button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Noch zu lizenzieren --}}
        <x-card class="mt-4" padding="p-0">
            <div class="border-b border-base-300 px-4 py-3">
                <h2 class="text-sm font-semibold uppercase tracking-wider opacity-60">{{ __('scope.growth.section.not_licensed') }}</h2>
            </div>
            @if ($byState['not_licensed'] === [])
                <x-empty-state icon="verified" :title="__('scope.growth.none_not_licensed')" compact />
            @else
                <ul class="divide-y divide-base-300">
                    @foreach ($byState['not_licensed'] as $row)
                        <li class="p-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{ __($row['label']) }}</span>
                                <x-status-badge tone="ghost">{{ __('scope.growth.state.not_licensed') }}</x-status-badge>
                            </div>
                            @if ($row['description'] !== '')
                                <p class="mt-0.5 text-xs text-base-content/70">{{ __($row['description']) }}</p>
                            @endif
                            <ul class="mt-1 space-y-0.5 text-xs text-muted">
                                @if ($row['packages'] !== [])
                                    <li>{{ __('scope.growth.in_packages', ['packages' => implode(', ', array_map(static fn (string $p): string => __($p), $row['packages']))]) }}</li>
                                @endif
                                @if ($row['requires'] !== [])
                                    <li>{{ __('scope.growth.requires', ['modules' => implode(', ', array_map($moduleLabel, $row['requires']))]) }}</li>
                                @endif
                            </ul>
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="border-t border-base-300 px-4 py-3 text-sm">
                <h3 class="font-semibold">{{ __('scope.growth.request_heading') }}</h3>
                <p class="mt-1 text-xs text-base-content/70">{{ __('scope.growth.request_hint') }}</p>
                <p class="mt-2 text-xs"><span class="text-muted">{{ __('scope.growth.license_uid') }}:</span> <code class="select-all font-mono">{{ $licenseUid }}</code></p>
            </div>
        </x-card>

        {{-- Gesperrt und aktiv (kompakt) --}}
        @if ($byState['blocked'] !== [])
            <x-card class="mt-4">
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider opacity-60">{{ __('scope.growth.state.blocked') }}</h2>
                <div class="flex flex-wrap gap-1">
                    @foreach ($byState['blocked'] as $row)
                        <x-status-badge tone="warning" outline>{{ __($row['label']) }}</x-status-badge>
                    @endforeach
                </div>
            </x-card>
        @endif
        <x-card class="mt-4">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider opacity-60">{{ __('scope.growth.section.active') }}</h2>
            <div class="flex flex-wrap gap-1">
                @forelse ($byState['active'] as $row)
                    <x-status-badge tone="success" outline>{{ __($row['label']) }}</x-status-badge>
                @empty
                    <span class="text-sm text-muted">{{ __('scope.growth.none_active') }}</span>
                @endforelse
            </div>
        </x-card>
    </x-page-shell>
@endsection
