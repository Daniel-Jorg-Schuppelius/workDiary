@extends('layouts.app')

@section('title', __('Branchenprofile'))
@section('nav-title', __('Branchenprofile'))

@section('content')
<x-page-shell gap="6">
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>
                <div>
                    <h2 class="text-xl font-semibold">{{ __('Branchenprofile') }}</h2>
                    <p class="text-sm text-base-content/60">
                        {{ __('Vorlagen für :org: Klassifikationen, Pflichtregeln und Tags in einem Schritt installieren.', ['org' => $organization->name]) }}
                    </p>
                </div>
            </x-slot:title>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('admin.branch-profiles.index')" :reset="route('admin.branch-profiles.index')">
        <x-filter-field :label="__('Suche')" for="branch-profile-q" class="min-w-64 flex-1">
                <input type="text"
                       id="branch-profile-q"
                       name="q"
                       value="{{ $activeFilters['q'] ?? '' }}"
                       class="input input-sm input-bordered w-full"
                       placeholder="{{ __('Code oder Bezeichnung') }}" />
        </x-filter-field>

        <x-filter-field :label="__('Installationsstatus')" for="branch-profile-installed">
                <select id="branch-profile-installed" name="installed" class="select select-sm select-bordered w-full">
                    <option value="all" @selected(($activeFilters['installed'] ?? 'all') === 'all')>{{ __('Alle') }}</option>
                    <option value="installed" @selected(($activeFilters['installed'] ?? 'all') === 'installed')>{{ __('Installiert') }}</option>
                    <option value="not_installed" @selected(($activeFilters['installed'] ?? 'all') === 'not_installed')>{{ __('Nicht installiert') }}</option>
                </select>
        </x-filter-field>
    </x-filter-bar>

    @if ($profiles->isEmpty())
        <x-card>
            <p class="text-sm text-base-content/70">{{ __('Keine Branchenprofile fuer den aktuellen Filter gefunden.') }}</p>
        </x-card>
    @else
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            @foreach ($profiles as $profile)
            <x-card>
                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">{{ $profile['label'] }}</h2>
                            <p class="text-sm text-base-content/60 font-mono">{{ $profile['code'] }} · v{{ $profile['version'] }}</p>
                        </div>
                        @if (in_array($profile['code'], $installedCodes, true))
                            <span class="badge badge-info badge-sm">{{ __('Bereits installiert') }}</span>
                        @endif
                    </div>

                    <div class="grid grid-cols-3 gap-3 text-sm">
                        <div class="rounded-box bg-base-200 px-3 py-2">
                            <div class="text-lg font-semibold">{{ $profile['classification_count'] }}</div>
                            <div class="text-base-content/60">{{ __('Klassifikationen') }}</div>
                        </div>
                        <div class="rounded-box bg-base-200 px-3 py-2">
                            <div class="text-lg font-semibold">{{ $profile['requirement_count'] }}</div>
                            <div class="text-base-content/60">{{ __('Pflichtregeln') }}</div>
                        </div>
                        <div class="rounded-box bg-base-200 px-3 py-2">
                            <div class="text-lg font-semibold">{{ $profile['tag_count'] }}</div>
                            <div class="text-base-content/60">{{ __('Tags') }}</div>
                        </div>
                    </div>

                    <div class="flex gap-2 justify-end">
                        <form method="POST" action="{{ route('admin.branch-profiles.install', $profile['code']) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary gap-2">
                                <x-icon name="playlist_add_check" /> {{ __('Installieren') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.branch-profiles.install', $profile['code']) }}">
                            @csrf
                            <input type="hidden" name="force" value="1" />
                            <button type="submit" class="btn btn-sm btn-outline gap-2">
                                <x-icon name="refresh" /> {{ __('Erneut anwenden') }}
                            </button>
                        </form>
                    </div>
                </div>
            </x-card>
            @endforeach
        </div>
    @endif
</x-page-shell>
@endsection
