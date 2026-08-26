{{--
  Created on   : Wed Jul 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('scope.title.index') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('scope.title.index'))

@php
    /** @var list<array{code:string,label:string,description:string,status:\App\Enums\Licensing\ModuleStatus,source:?string,reason:?string,licensed:bool,available:bool}> $modules */
    /** @var array<string, array{label:string,description:string,modules:list<string>|null}> $presets */
    /** @var array{code:string,label:string,modules:list<string>}|null $recommendation */
    /** @var string|null $scopeConfiguredAt */
@endphp

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('scope.page.subtitle')">
                <x-slot:actions>
                    <x-button href="{{ route('admin.license.index') }}" tone="ghost" size="sm" icon="key">{{ __('Lizenz') }}</x-button>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <div class="alert alert-info rounded-2xl px-5 py-3 text-sm shadow-xs">
            <x-icon name="info" class="text-base" />
            <span>{{ __('scope.page.no_data_loss') }}</span>
        </div>

        {{-- Preset-Schnellwahl --}}
        <x-card class="mt-4">
            <h2 class="mb-1 text-sm font-semibold uppercase tracking-wider opacity-60">{{ __('scope.presets.heading') }}</h2>
            <p class="mb-3 text-sm text-base-content/70">{{ __('scope.presets.hint') }}</p>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($presets as $presetKey => $preset)
                    <form method="POST" action="{{ route('admin.scope.save') }}" class="flex">
                        @csrf
                        <input type="hidden" name="preset" value="{{ $presetKey }}">
                        <button type="submit"
                                class="card w-full cursor-pointer border border-base-300 bg-base-200/40 p-4 text-left transition hover:border-primary hover:shadow-sm"
                                title="{{ __('scope.presets.apply', ['preset' => __($preset['label'])]) }}">
                            <span class="font-semibold">{{ __($preset['label']) }}</span>
                            <span class="mt-1 block text-xs text-base-content/70">{{ __($preset['description']) }}</span>
                            <span class="mt-2 block text-xs font-medium text-primary">
                                {{ $preset['modules'] === null ? __('scope.presets.all_modules') : trans_choice('scope.presets.module_count', count($preset['modules']), ['count' => count($preset['modules'])]) }}
                            </span>
                        </button>
                    </form>
                @endforeach
            </div>
        </x-card>

        {{-- Branchenprofil-Empfehlung --}}
        @if ($recommendation !== null)
            <x-card class="mt-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold uppercase tracking-wider opacity-60">{{ __('scope.recommendation.heading') }}</h2>
                        <p class="mt-1 text-sm text-base-content/70">
                            {{ __('scope.recommendation.hint', ['profile' => $recommendation['label']]) }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach ($recommendation['modules'] as $code)
                                <span class="badge badge-outline badge-sm">{{ __((string) (config('plans.labels')[$code] ?? $code)) }}</span>
                            @endforeach
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.scope.save') }}">
                        @csrf
                        <input type="hidden" name="apply_recommendation" value="1">
                        <x-button type="submit" tone="primary" size="sm" icon="storefront">{{ __('scope.recommendation.apply') }}</x-button>
                    </form>
                </div>
            </x-card>
        @endif

        {{-- Modul-Checkliste --}}
        <form method="POST" action="{{ route('admin.scope.save') }}" class="mt-4">
            @csrf
            <x-card padding="p-0">
                <div class="flex items-center justify-between gap-3 border-b border-base-300 px-4 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wider opacity-60">{{ __('scope.modules.heading') }}</h2>
                    @if ($scopeConfiguredAt)
                        <span class="text-xs opacity-60">{{ __('scope.modules.configured_at', ['date' => \Carbon\CarbonImmutable::parse($scopeConfiguredAt)->format('d.m.Y H:i')]) }}</span>
                    @endif
                </div>
                <ul class="divide-y divide-base-300">
                    @foreach ($modules as $module)
                        <li class="flex items-start gap-3 p-3">
                            <label class="label cursor-pointer gap-2 pt-0.5 {{ $module['status']->isConfigurable() ? '' : 'opacity-50' }}">
                                <input type="checkbox"
                                       name="modules[]"
                                       value="{{ $module['code'] }}"
                                       class="toggle toggle-primary toggle-sm"
                                       @checked($module['status'] === \App\Enums\Licensing\ModuleStatus::Active)
                                       @disabled(! $module['status']->isConfigurable())>
                            </label>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold">{{ __($module['label']) }}</span>
                                    <span class="badge badge-{{ $module['status']->tone() }} badge-sm">{{ $module['status']->label() }}</span>
                                </div>
                                @if ($module['description'] !== '')
                                    <p class="mt-0.5 text-xs text-base-content/70">{{ __($module['description']) }}</p>
                                @endif
                                @if ($module['status'] === \App\Enums\Licensing\ModuleStatus::NotLicensed)
                                    <p class="mt-0.5 text-xs text-muted">{{ __('scope.modules.not_licensed_hint') }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <div class="mt-4 flex justify-end">
                <x-button type="submit" tone="primary" size="sm" icon="save">{{ __('Speichern') }}</x-button>
            </div>
        </form>
    </x-page-shell>
@endsection
