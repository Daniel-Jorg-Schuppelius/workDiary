{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('WebDAV'))
@section('nav-title', __('WebDAV'))

@section('content')
<x-index-page :title="__('webdav::webdav.title')" :subtitle="__('webdav::webdav.intro')">
    <x-slot:badges>
        @if ($connection && $connection->isActive())
            <x-plugin-health :plugin-id="\App\Plugins\Webdav\WebdavPlugin::ID" :state="$healthState" />
        @elseif ($connection)
            <x-status-badge>{{ __('webdav::webdav.health.inactive') }}</x-status-badge>
        @endif
    </x-slot:badges>

    <x-validation-errors first />

    {{-- Status + Aktionen --}}
    <x-card>
        @if ($connection && $connection->isActive())
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.webdav.mirror') }}">
                    @csrf
                    <x-button type="submit">{{ __('webdav::webdav.action.mirror') }}</x-button>
                </form>
                <form method="POST" action="{{ route('admin.webdav.disconnect') }}">
                    @csrf
                    <x-button type="submit" tone="ghost">{{ __('webdav::webdav.action.disconnect') }}</x-button>
                </form>
            </div>
        @endif
    </x-card>

    {{-- Ablage --}}
    <x-card as="form" class="space-y-3" method="POST" action="{{ route('admin.webdav.connection.store') }}">
        @csrf
        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('webdav::webdav.connection.heading') }}</h2>

        <div class="grid gap-3 md:grid-cols-2">
            <label class="form-control">
                <span class="label-text">{{ __('webdav::webdav.field.name') }}</span>
                <input type="text" name="name" value="{{ old('name', $connection->name ?? '') }}"
                       class="input input-bordered input-sm" required>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('webdav::webdav.field.base_url') }}</span>
                <input type="url" name="base_url" value="{{ old('base_url', $connection->base_url ?? '') }}"
                       placeholder="https://cloud.example.com/remote.php/dav/files/svc/WorkDiary" class="input input-bordered input-sm" required>
                <span class="label-text-alt text-muted">{{ __('webdav::webdav.field.base_url_help') }}</span>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('webdav::webdav.field.username') }}</span>
                <input type="text" name="username" value="{{ old('username', $connection->username ?? '') }}"
                       autocomplete="off" class="input input-bordered input-sm" required>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('webdav::webdav.field.app_password') }}</span>
                <input type="password" name="app_password" autocomplete="new-password"
                       placeholder="{{ $connection ? __('webdav::webdav.field.password_keep') : '' }}"
                       class="input input-bordered input-sm" @required(! $connection)>
                <span class="label-text-alt text-muted">{{ __('webdav::webdav.field.password_help') }}</span>
            </label>
            <label class="form-control">
                <span class="label-text">{{ __('webdav::webdav.field.default_folder') }}</span>
                <input type="text" name="default_folder" value="{{ old('default_folder', $connection->default_folder ?? 'Dokumente') }}"
                       class="input input-bordered input-sm" required>
            </label>
            <label class="form-control justify-end">
                <span class="label cursor-pointer justify-start gap-2">
                    <input type="hidden" name="allow_private_network" value="0">
                    <input type="checkbox" name="allow_private_network" value="1" class="toggle toggle-sm toggle-warning"
                           @checked(old('allow_private_network', $connection->allow_private_network ?? false))>
                    <span class="label-text">{{ __('webdav::webdav.field.allow_private_network') }}</span>
                </span>
                <span class="label-text-alt text-muted">{{ __('webdav::webdav.field.allow_private_network_help') }}</span>
            </label>
            <label class="form-control justify-end">
                <span class="label cursor-pointer justify-start gap-2">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" class="toggle toggle-sm toggle-primary"
                           @checked(old('active', $connection->active ?? true))>
                    <span class="label-text">{{ __('webdav::webdav.field.active') }}</span>
                </span>
            </label>
        </div>

        {{-- Spiegel-Quellen (Rang 19): Dokumente / Rechnungen / Protokolle. --}}
        @php $currentSources = (array) old('sources', $connection->sources ?? ['document']); @endphp
        <div class="form-control">
            <span class="label-text">{{ __('webdav::webdav.field.sources') }}</span>
            <div class="flex flex-wrap gap-4 pt-1">
                @foreach (\App\Plugins\Webdav\Models\WebdavConnection::SOURCES as $source)
                    <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" name="sources[]" value="{{ $source }}" class="checkbox checkbox-sm"
                               @checked(in_array($source, $currentSources, true))>
                        <span class="label-text">{{ __('webdav::webdav.field.source_' . $source) }}</span>
                    </label>
                @endforeach
            </div>
            <span class="label-text-alt text-muted">{{ __('webdav::webdav.field.sources_help') }}</span>
        </div>

        {{-- Dokumenttyp → Ordner --}}
        <div>
            <h3 class="mb-1 text-sm font-semibold">{{ __('webdav::webdav.folder.heading') }}</h3>
            <p class="mb-2 text-xs text-muted">{{ __('webdav::webdav.folder.help') }}</p>
            <div class="space-y-2">
                @php $map = $connection->folder_map ?? []; @endphp
                @foreach (array_merge(array_keys($map), array_fill(0, 3, '')) as $mapType)
                    <div class="flex flex-wrap items-center gap-2">
                        <select name="folder_type[]" class="select select-bordered select-sm w-56">
                            <option value="">{{ __('webdav::webdav.folder.type_placeholder') }}</option>
                            @foreach ($documentTypes as $type)
                                <option value="{{ $type->value }}" @selected($mapType === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        <span class="text-muted">→</span>
                        <input aria-label="{{ __('webdav::webdav.folder.path_placeholder') }}" type="text" name="folder_path[]" value="{{ $mapType !== '' ? ($map[$mapType] ?? '') : '' }}"
                               placeholder="{{ __('webdav::webdav.folder.path_placeholder') }}" class="input input-bordered input-sm w-64">
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <x-button type="submit">{{ __('webdav::webdav.action.save') }}</x-button>
        </div>
    </x-card>
</x-index-page>
@endsection
