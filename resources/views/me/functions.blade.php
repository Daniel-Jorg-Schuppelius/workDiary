@extends('layouts.app')

@section('title', __('scope.nav.functions'))
@section('nav-title', __('scope.nav.functions'))

@php
    /** @var list<array{key:string,label:string,hidden:bool,entries:list<array<string,mixed>>}> $sections */
    /** @var bool $canManageScope */
    /** @var bool $focusActive */
    /** @var string $activeFocusLabel */
@endphp

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('scope.functions.subtitle')">
                <x-slot:actions>
                    <x-button href="{{ route('me.navigation.customize') }}" tone="ghost" size="sm" icon="edit">{{ __('scope.nav.customize') }}</x-button>
                    @if ($canManageScope)
                        <x-button href="{{ route('admin.scope.index') }}" tone="ghost" size="sm" icon="tune">{{ __('scope.title.index') }}</x-button>
                    @endif
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        {{-- Aktiver Arbeitsbereich (Feature 082, MVP-380): Hinweis + Rückweg zur
             Vollansicht. Fokus-ausgeblendete Einträge sind unten markiert, bleiben
             hier aber direkt erreichbar. --}}
        @if ($focusActive)
            <div class="alert alert-info mb-4 flex-wrap gap-2 rounded-2xl px-5 py-3 text-sm shadow-xs">
                <x-icon name="filter_alt" class="text-base" />
                <span class="flex-1">{{ __('scope.functions.focus_banner', ['name' => $activeFocusLabel]) }}</span>
                <form method="POST" action="{{ route('me.focus.switch', 'all') }}">
                    @csrf
                    <x-button type="submit" tone="ghost" size="xs" icon="apps">{{ __('scope.functions.show_all') }}</x-button>
                </form>
            </div>
        @endif

        @foreach ($sections as $section)
            <x-card padding="p-0" class="{{ $loop->first ? '' : 'mt-4' }}">
                <div class="flex items-center gap-2 border-b border-base-300 px-4 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wider opacity-60">{{ $section['label'] }}</h2>
                    @if ($section['hidden'])
                        <span class="badge badge-neutral badge-sm">{{ __('scope.functions.state.hidden_section') }}</span>
                        <form method="POST" action="{{ route('me.navigation.unhide') }}" class="ml-auto">
                            @csrf
                            <input type="hidden" name="key" value="{{ \App\Services\Navigation\NavigationRegistry::KEY_SECTION . $section['key'] }}">
                            <x-button type="submit" tone="ghost" size="xs" icon="visibility">{{ __('scope.functions.action.unhide') }}</x-button>
                        </form>
                    @endif
                </div>
                <ul class="divide-y divide-base-300">
                    @foreach ($section['entries'] as $entry)
                        <li class="flex items-center gap-3 p-3">
                            <x-icon :name="$entry['icon']" class="text-[1.1rem] opacity-70" />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($entry['visible'])
                                        <a href="{{ route($entry['route']) }}" class="link link-hover font-medium">{{ $entry['label'] }}</a>
                                    @else
                                        <span class="font-medium text-base-content/60">{{ $entry['label'] }}</span>
                                    @endif
                                    @if ($entry['status'] === \App\Enums\Licensing\ModuleStatus::NotLicensed)
                                        <span class="badge badge-ghost badge-sm">{{ __('Nicht lizenziert') }}</span>
                                    @elseif ($entry['status'] === \App\Enums\Licensing\ModuleStatus::InactiveByCustomer)
                                        <span class="badge badge-neutral badge-sm">{{ __('scope.functions.state.org_disabled') }}</span>
                                    @elseif ($entry['status'] === \App\Enums\Licensing\ModuleStatus::Blocked)
                                        <span class="badge badge-warning badge-sm">{{ __('Gesperrt') }}</span>
                                    @elseif ($entry['hidden'])
                                        <span class="badge badge-neutral badge-sm">{{ __('scope.functions.state.hidden_by_me') }}</span>
                                    @elseif ($entry['in_focus_hidden'])
                                        <span class="badge badge-info badge-sm">{{ __('scope.functions.in_focus_hidden') }}</span>
                                    @endif
                                </div>
                                @if ($entry['status'] === \App\Enums\Licensing\ModuleStatus::NotLicensed && $entry['module_description'])
                                    <p class="mt-0.5 text-xs text-base-content/60">{{ __($entry['module_description']) }} {{ __('scope.functions.upsell_hint') }}</p>
                                @endif
                            </div>
                            @if ($entry['status'] === \App\Enums\Licensing\ModuleStatus::InactiveByCustomer && $canManageScope)
                                <x-button href="{{ route('admin.scope.index') }}" tone="ghost" size="xs" icon="tune">{{ __('scope.functions.action.enable_module') }}</x-button>
                            @elseif ($entry['hidden'] && ($entry['status'] === null || $entry['status'] === \App\Enums\Licensing\ModuleStatus::Active))
                                <form method="POST" action="{{ route('me.navigation.unhide') }}">
                                    @csrf
                                    <input type="hidden" name="key" value="{{ $entry['key'] }}">
                                    <x-button type="submit" tone="ghost" size="xs" icon="visibility">{{ __('scope.functions.action.unhide') }}</x-button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endforeach
    </x-page-shell>
@endsection
