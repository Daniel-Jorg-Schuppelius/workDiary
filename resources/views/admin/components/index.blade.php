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
                </div>
            </div>
        </article>
    </div>
</x-index-page>
@endsection
