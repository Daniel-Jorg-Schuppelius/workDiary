{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('sharepoint::sharepoint.title'))
@section('nav-title', __('sharepoint::sharepoint.title'))

@section('content')
<x-index-page :subtitle="__('sharepoint::sharepoint.intro')">
    <x-slot:badges>
        @if ($connection && $connection->isActive())
            <x-plugin-health :plugin-id="\App\Plugins\Sharepoint\SharepointPlugin::ID" :state="$healthState" />
        @elseif ($connection)
            <x-status-badge>{{ __('sharepoint::sharepoint.health.badge_inactive') }}</x-status-badge>
        @endif
    </x-slot:badges>

    <x-validation-errors first />

    {{-- Status + Aktionen --}}
    <x-card>
        @unless ($configured)
            <div role="alert" class="alert alert-warning text-sm">{{ __('sharepoint::sharepoint.not_configured_hint') }}</div>
        @endunless

        @if ($connection && $connection->status === \App\Plugins\Support\OAuthConnectionStatus::Active)
            <div class="flex flex-wrap gap-2">
                @if ($connection->isActive())
                    <form method="POST" action="{{ route('admin.sharepoint.mirror') }}">
                        @csrf
                        <x-button type="submit">{{ __('sharepoint::sharepoint.action.mirror') }}</x-button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.sharepoint.disconnect') }}">
                    @csrf
                    <x-button type="submit" tone="ghost">{{ __('sharepoint::sharepoint.action.disconnect') }}</x-button>
                </form>
            </div>
        @elseif ($configured)
            <form method="POST" action="{{ route('admin.sharepoint.oauth.start') }}">
                @csrf
                <x-button type="submit">{{ __('sharepoint::sharepoint.action.connect') }}</x-button>
            </form>
        @endif
    </x-card>

    {{-- Ziel: Site + Dokumentbibliothek --}}
    @if ($connection && $connection->status === \App\Plugins\Support\OAuthConnectionStatus::Active)
        <x-card class="space-y-3">
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('sharepoint::sharepoint.target.heading') }}</h2>
            <p class="text-sm text-muted">{{ __('sharepoint::sharepoint.target.help') }}</p>

            @if ($connection->site_id)
                <p class="text-sm">
                    {{ __('sharepoint::sharepoint.target.current') }}:
                    <span class="font-semibold">{{ $connection->site_name ?? $connection->site_id }}</span>
                    →
                    <span class="font-semibold">{{ $connection->drive_name ?? $connection->drive_id }}</span>
                </p>
            @endif

            {{-- Schritt 1: Site suchen (GET, lädt Ergebnisse serverseitig). --}}
            <form method="GET" action="{{ route('admin.sharepoint.index') }}" class="flex flex-wrap items-end gap-2">
                <label class="form-control max-w-md grow">
                    <span class="label-text">{{ __('sharepoint::sharepoint.target.search') }}</span>
                    <input type="text" name="site_search" value="{{ $siteSearch }}"
                           placeholder="{{ __('sharepoint::sharepoint.target.search_placeholder') }}" class="input input-bordered input-sm">
                </label>
                <x-button type="submit" tone="plain">{{ __('sharepoint::sharepoint.target.search_action') }}</x-button>
            </form>

            @if ($siteSearch !== '' && $sites === [])
                <x-empty-state icon="search_off" :title="__('sharepoint::sharepoint.target.no_sites')" compact />
            @endif

            @if ($sites !== [])
                {{-- Schritt 2: Site wählen → Bibliotheken der Site laden (GET). --}}
                <div class="space-y-1">
                    @foreach ($sites as $site)
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <a class="link link-primary"
                               href="{{ route('admin.sharepoint.index', ['site_search' => $siteSearch, 'site_id' => $site['id']]) }}">{{ $site['name'] }}</a>
                            <span class="text-xs text-muted">{{ $site['url'] }}</span>
                            @if ($selectedSiteId === $site['id'])
                                <x-status-badge tone="plain" outline>{{ __('sharepoint::sharepoint.target.selected') }}</x-status-badge>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($selectedSiteId !== '' && $drives !== [])
                {{-- Schritt 3: Bibliothek wählen (POST, serverseitig validiert). --}}
                <form method="POST" action="{{ route('admin.sharepoint.target.store') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="hidden" name="site_id" value="{{ $selectedSiteId }}">
                    <label class="form-control max-w-md grow">
                        <span class="label-text">{{ __('sharepoint::sharepoint.target.drive') }}</span>
                        <select name="drive_id" class="select select-bordered select-sm">
                            @foreach ($drives as $drive)
                                <option value="{{ $drive['id'] }}" @selected($connection->drive_id === $drive['id'])>{{ $drive['name'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-button type="submit">{{ __('sharepoint::sharepoint.action.save') }}</x-button>
                </form>
            @elseif ($selectedSiteId !== '')
                <x-empty-state icon="folder_off" :title="__('sharepoint::sharepoint.target.no_drives')" compact />
            @endif
        </x-card>

        {{-- Ordnerregeln + Quellen (WebDAV-Muster) --}}
        <x-card as="form" class="space-y-3" method="POST" action="{{ route('admin.sharepoint.settings.store') }}">
            @csrf
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('sharepoint::sharepoint.settings.heading') }}</h2>

            <div class="grid gap-3 md:grid-cols-2">
                <label class="form-control">
                    <span class="label-text">{{ __('sharepoint::sharepoint.field.default_folder') }}</span>
                    <input type="text" name="default_folder" value="{{ old('default_folder', $connection->default_folder ?? 'Dokumente') }}"
                           class="input input-bordered input-sm" required>
                </label>
                <label class="form-control justify-end">
                    <span class="label cursor-pointer justify-start gap-2">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" class="toggle toggle-sm toggle-primary"
                               @checked(old('active', $connection->active ?? true))>
                        <span class="label-text">{{ __('sharepoint::sharepoint.field.active') }}</span>
                    </span>
                </label>
            </div>

            {{-- Spiegel-Quellen: Dokumente / Rechnungen / Protokolle. --}}
            @php $currentSources = (array) old('sources', $connection->sources ?? ['document']); @endphp
            <div class="form-control">
                <span class="label-text">{{ __('sharepoint::sharepoint.field.sources') }}</span>
                <div class="flex flex-wrap gap-4 pt-1">
                    @foreach (\App\Plugins\Sharepoint\Models\SharepointConnection::SOURCES as $source)
                        <label class="label cursor-pointer justify-start gap-2">
                            <input type="checkbox" name="sources[]" value="{{ $source }}" class="checkbox checkbox-sm"
                                   @checked(in_array($source, $currentSources, true))>
                            <span class="label-text">{{ __('sharepoint::sharepoint.field.source_' . $source) }}</span>
                        </label>
                    @endforeach
                </div>
                <span class="label-text-alt text-muted">{{ __('sharepoint::sharepoint.field.sources_help') }}</span>
            </div>

            {{-- Dokumenttyp → Ordner --}}
            <div>
                <h3 class="mb-1 text-sm font-semibold">{{ __('sharepoint::sharepoint.folder.heading') }}</h3>
                <p class="mb-2 text-xs text-muted">{{ __('sharepoint::sharepoint.folder.help') }}</p>
                <div class="space-y-2">
                    @php $map = $connection->folder_map ?? []; @endphp
                    @foreach (array_merge(array_keys($map), array_fill(0, 3, '')) as $mapType)
                        <div class="flex flex-wrap items-center gap-2">
                            <select name="folder_type[]" class="select select-bordered select-sm w-56">
                                <option value="">{{ __('sharepoint::sharepoint.folder.type_placeholder') }}</option>
                                @foreach ($documentTypes as $type)
                                    <option value="{{ $type->value }}" @selected($mapType === $type->value)>{{ $type->value }}</option>
                                @endforeach
                            </select>
                            <span class="text-muted">→</span>
                            <input aria-label="{{ __('sharepoint::sharepoint.folder.path_placeholder') }}" type="text" name="folder_path[]" value="{{ $mapType !== '' ? ($map[$mapType] ?? '') : '' }}"
                                   placeholder="{{ __('sharepoint::sharepoint.folder.path_placeholder') }}" class="input input-bordered input-sm w-64">
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end">
                <x-button type="submit">{{ __('sharepoint::sharepoint.action.save') }}</x-button>
            </div>
        </x-card>
    @endif
</x-index-page>
@endsection
