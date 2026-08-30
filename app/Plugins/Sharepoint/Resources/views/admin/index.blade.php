{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('sharepoint.title'))
@section('nav-title', __('sharepoint.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error text-sm">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error text-sm">{{ $errors->first() }}</div>
        @endif

        {{-- Status + Aktionen --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('sharepoint.title') }}</h1>
                @if ($connection && $connection->isActive())
                    @if (($health['ok'] ?? false))
                        <span class="badge badge-success badge-sm">{{ __('sharepoint.health.badge_ok') }}</span>
                    @else
                        <span class="badge badge-error badge-sm">{{ __('sharepoint.health.badge_failing') }}</span>
                    @endif
                @elseif ($connection)
                    <span class="badge badge-ghost badge-sm">{{ __('sharepoint.health.badge_inactive') }}</span>
                @endif
            </div>
            <p class="mb-4 text-sm text-muted">{{ __('sharepoint.intro') }}</p>

            @unless ($configured)
                <div class="alert alert-warning text-sm">{{ __('sharepoint.not_configured_hint') }}</div>
            @endunless

            @if ($connection && $connection->status === \App\Models\SharepointConnection::STATUS_ACTIVE)
                <div class="flex flex-wrap gap-2">
                    @if ($connection->isActive())
                        <form method="POST" action="{{ route('admin.sharepoint.mirror') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('sharepoint.action.mirror') }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.sharepoint.disconnect') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-ghost">{{ __('sharepoint.action.disconnect') }}</button>
                    </form>
                </div>
            @elseif ($configured)
                <form method="POST" action="{{ route('admin.sharepoint.oauth.start') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('sharepoint.action.connect') }}</button>
                </form>
            @endif
        </div>

        {{-- Ziel: Site + Dokumentbibliothek --}}
        @if ($connection && $connection->status === \App\Models\SharepointConnection::STATUS_ACTIVE)
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('sharepoint.target.heading') }}</h2>
                <p class="text-sm text-muted">{{ __('sharepoint.target.help') }}</p>

                @if ($connection->site_id)
                    <p class="text-sm">
                        {{ __('sharepoint.target.current') }}:
                        <span class="font-semibold">{{ $connection->site_name ?? $connection->site_id }}</span>
                        →
                        <span class="font-semibold">{{ $connection->drive_name ?? $connection->drive_id }}</span>
                    </p>
                @endif

                {{-- Schritt 1: Site suchen (GET, lädt Ergebnisse serverseitig). --}}
                <form method="GET" action="{{ route('admin.sharepoint.index') }}" class="flex flex-wrap items-end gap-2">
                    <label class="form-control max-w-md grow">
                        <span class="label-text">{{ __('sharepoint.target.search') }}</span>
                        <input type="text" name="site_search" value="{{ $siteSearch }}"
                               placeholder="{{ __('sharepoint.target.search_placeholder') }}" class="input input-bordered input-sm">
                    </label>
                    <button type="submit" class="btn btn-sm">{{ __('sharepoint.target.search_action') }}</button>
                </form>

                @if ($siteSearch !== '' && $sites === [])
                    <p class="text-sm text-muted">{{ __('sharepoint.target.no_sites') }}</p>
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
                                    <span class="badge badge-sm badge-outline">{{ __('sharepoint.target.selected') }}</span>
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
                            <span class="label-text">{{ __('sharepoint.target.drive') }}</span>
                            <select name="drive_id" class="select select-bordered select-sm">
                                @foreach ($drives as $drive)
                                    <option value="{{ $drive['id'] }}" @selected($connection->drive_id === $drive['id'])>{{ $drive['name'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('sharepoint.action.save') }}</button>
                    </form>
                @elseif ($selectedSiteId !== '')
                    <p class="text-sm text-muted">{{ __('sharepoint.target.no_drives') }}</p>
                @endif
            </div>

            {{-- Ordnerregeln + Quellen (WebDAV-Muster) --}}
            <form method="POST" action="{{ route('admin.sharepoint.settings.store') }}"
                  class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
                @csrf
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('sharepoint.settings.heading') }}</h2>

                <div class="grid gap-3 md:grid-cols-2">
                    <label class="form-control">
                        <span class="label-text">{{ __('sharepoint.field.default_folder') }}</span>
                        <input type="text" name="default_folder" value="{{ old('default_folder', $connection->default_folder ?? 'Dokumente') }}"
                               class="input input-bordered input-sm" required>
                    </label>
                    <label class="form-control justify-end">
                        <span class="label cursor-pointer justify-start gap-2">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" class="toggle toggle-sm toggle-primary"
                                   @checked(old('active', $connection->active ?? true))>
                            <span class="label-text">{{ __('sharepoint.field.active') }}</span>
                        </span>
                    </label>
                </div>

                {{-- Spiegel-Quellen: Dokumente / Rechnungen / Protokolle. --}}
                @php $currentSources = (array) old('sources', $connection->sources ?? ['document']); @endphp
                <div class="form-control">
                    <span class="label-text">{{ __('sharepoint.field.sources') }}</span>
                    <div class="flex flex-wrap gap-4 pt-1">
                        @foreach (\App\Models\SharepointConnection::SOURCES as $source)
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="checkbox" name="sources[]" value="{{ $source }}" class="checkbox checkbox-sm"
                                       @checked(in_array($source, $currentSources, true))>
                                <span class="label-text">{{ __('sharepoint.field.source_' . $source) }}</span>
                            </label>
                        @endforeach
                    </div>
                    <span class="label-text-alt text-muted">{{ __('sharepoint.field.sources_help') }}</span>
                </div>

                {{-- Dokumenttyp → Ordner --}}
                <div>
                    <h3 class="mb-1 text-sm font-semibold">{{ __('sharepoint.folder.heading') }}</h3>
                    <p class="mb-2 text-xs text-muted">{{ __('sharepoint.folder.help') }}</p>
                    <div class="space-y-2">
                        @php $map = $connection->folder_map ?? []; @endphp
                        @foreach (array_merge(array_keys($map), array_fill(0, 3, '')) as $mapType)
                            <div class="flex flex-wrap items-center gap-2">
                                <select name="folder_type[]" class="select select-bordered select-sm w-56">
                                    <option value="">{{ __('sharepoint.folder.type_placeholder') }}</option>
                                    @foreach ($documentTypes as $type)
                                        <option value="{{ $type->value }}" @selected($mapType === $type->value)>{{ $type->value }}</option>
                                    @endforeach
                                </select>
                                <span class="text-muted">→</span>
                                <input aria-label="{{ __('sharepoint.folder.path_placeholder') }}" type="text" name="folder_path[]" value="{{ $mapType !== '' ? ($map[$mapType] ?? '') : '' }}"
                                       placeholder="{{ __('sharepoint.folder.path_placeholder') }}" class="input input-bordered input-sm w-64">
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('sharepoint.action.save') }}</button>
                </div>
            </form>
        @endif
    </div>
</x-page-shell>
@endsection
