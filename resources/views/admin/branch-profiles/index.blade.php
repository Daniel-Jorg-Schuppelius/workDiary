@extends('layouts.app')

@section('title', __('Branchenprofile'))
@section('nav-title', __('Branchenprofile'))

@section('content')
<x-index-page :subtitle="__('Vorlagen für :org: Klassifikationen, Pflichtregeln und Tags in einem Schritt installieren.', ['org' => $organization->name])">

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
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">store</span>'
            :title="__('Keine Branchenprofile für den aktuellen Filter gefunden.')" />
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
                            @php $appliedVersion = (int) ($installedVersions[$profile['code']] ?? 0); @endphp
                            @if ($appliedVersion > 0 && (int) $profile['version'] > $appliedVersion)
                                <x-status-badge tone="warning" size="sm">{{ __('Update: v:new (installiert v:old)', ['new' => $profile['version'], 'old' => $appliedVersion]) }}</x-status-badge>
                            @else
                                <x-status-badge tone="info" size="sm">{{ __('Bereits installiert') }}</x-status-badge>
                            @endif
                        @endif
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                        <div class="rounded-box bg-base-200 px-3 py-2">
                            <div class="text-lg font-semibold">{{ $profile['entry_type_count'] }}</div>
                            <div class="text-base-content/60">{{ __('Auftragsarten') }}</div>
                        </div>
                        <div class="rounded-box bg-base-200 px-3 py-2">
                            <div class="text-lg font-semibold">{{ $profile['classification_count'] }}</div>
                            <div class="text-base-content/60">{{ __('Kategorien') }}</div>
                        </div>
                        <div class="rounded-box bg-base-200 px-3 py-2">
                            <div class="text-lg font-semibold">{{ $profile['requirement_count'] }}</div>
                            <div class="text-base-content/60">{{ __('Pflichtregeln') }}</div>
                        </div>
                        <div class="rounded-box bg-base-200 px-3 py-2">
                            <div class="text-lg font-semibold">{{ $profile['procedure_count'] }}</div>
                            <div class="text-base-content/60">{{ __('Checklisten') }}</div>
                        </div>
                        <div class="rounded-box bg-base-200 px-3 py-2">
                            <div class="text-lg font-semibold">{{ $profile['room_requirement_count'] }}</div>
                            <div class="text-base-content/60">{{ __('Raumanforderungen') }}</div>
                        </div>
                        <div class="rounded-box bg-base-200 px-3 py-2">
                            <div class="text-lg font-semibold">{{ $profile['tag_count'] }}</div>
                            <div class="text-base-content/60">{{ __('Tags') }}</div>
                        </div>
                    </div>

                    @if (! empty($profile['entry_types']) || ! empty($profile['procedures']))
                        <div class="space-y-3 text-sm">
                            @if (! empty($profile['entry_types']))
                                <div>
                                    <div class="text-base-content/60 mb-1">{{ __('Enthaltene Auftragsarten') }}</div>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($profile['entry_types'] as $entryType)
                                            <span class="badge badge-ghost badge-sm">{{ $entryType }}</span>
                                        @endforeach
                                        @if ($profile['entry_type_count'] > count($profile['entry_types']))
                                            <span class="badge badge-sm">+{{ $profile['entry_type_count'] - count($profile['entry_types']) }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @if (! empty($profile['procedures']))
                                <div>
                                    <div class="text-base-content/60 mb-1">{{ __('Enthaltene Checklisten') }}</div>
                                    <ul class="list-disc list-inside text-base-content/80">
                                        @foreach ($profile['procedures'] as $procedure)
                                            <li>{{ $procedure }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif

                    @php
                        $installConfirm = __('Paket ":profile" für :org installieren? Bestehende, lokal angepasste Daten bleiben unberührt.', ['profile' => $profile['label'], 'org' => $organization->name]);
                        $reapplyConfirm = __('Paket ":profile" erneut anwenden? Vorlagen werden auf den Profilstand zurückgesetzt; veröffentlichte Checklisten bleiben erhalten.', ['profile' => $profile['label']]);
                    @endphp
                    <div class="flex gap-2 justify-end">
                        <x-action-form :action="route('admin.branch-profiles.install', $profile['code'])"
                              :confirm="$installConfirm"
                              confirm-icon="playlist_add_check"
                              :confirm-label="__('Installieren')">
                            <x-button type="submit" tone="primary" size="sm" class="gap-2" icon="playlist_add_check">{{ __('Installieren') }}</x-button>
                        </x-action-form>
                        <x-action-form :action="route('admin.branch-profiles.install', $profile['code'])"
                              :confirm="$reapplyConfirm"
                              confirm-icon="refresh"
                              confirm-tone="warning"
                              :confirm-label="__('Erneut anwenden')">
                            <input type="hidden" name="force" value="1" />
                            <x-button type="submit" tone="outline" size="sm" class="gap-2" icon="refresh">{{ __('Erneut anwenden') }}</x-button>
                        </x-action-form>
                    </div>
                </div>
            </x-card>
            @endforeach
        </div>
    @endif

    {{-- Marketplace-Import (Restpunkt 042): kuratiertes JSON-Profil hochladen. --}}
    <x-card :title="__('Profil importieren')">
        <p class="mb-2 text-xs text-base-content/60">{{ __('JSON-Profil (Struktur wie die mitgelieferten Branchenprofile). Klassifikations-Domänen sind hart begrenzt — unbekannte Domänen werden abgelehnt.') }}</p>
        <form method="POST" action="{{ route('admin.branch-profiles.import') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
            @csrf
            <input type="file" name="file" accept=".json,application/json" class="file-input file-input-bordered file-input-sm max-w-64" required>
            <x-icon-btn icon="upload_file" tone="primary" size="sm" type="submit" show-label>{{ __('Importieren & installieren') }}</x-icon-btn>
        </form>
    </x-card>
</x-index-page>
@endsection
