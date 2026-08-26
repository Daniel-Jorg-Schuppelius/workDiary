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
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('webdav.title') }}</h1>
                @if ($connection && $connection->isActive())
                    @if (($health['ok'] ?? false))
                        <span class="badge badge-success badge-sm">{{ __('webdav.health.ok') }}</span>
                    @else
                        <span class="badge badge-error badge-sm">{{ __('webdav.health.failing') }}</span>
                    @endif
                @elseif ($connection)
                    <span class="badge badge-ghost badge-sm">{{ __('webdav.health.inactive') }}</span>
                @endif
            </div>
            <p class="mb-4 text-sm text-muted">{{ __('webdav.intro') }}</p>

            @if ($connection && $connection->isActive())
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.webdav.mirror') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('webdav.action.mirror') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.webdav.disconnect') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-ghost">{{ __('webdav.action.disconnect') }}</button>
                    </form>
                </div>
            @endif
        </div>

        {{-- Ablage --}}
        <form method="POST" action="{{ route('admin.webdav.connection.store') }}"
              class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
            @csrf
            <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('webdav.connection.heading') }}</h2>

            <div class="grid gap-3 md:grid-cols-2">
                <label class="form-control">
                    <span class="label-text">{{ __('webdav.field.name') }}</span>
                    <input type="text" name="name" value="{{ old('name', $connection->name ?? '') }}"
                           class="input input-bordered input-sm" required>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('webdav.field.base_url') }}</span>
                    <input type="url" name="base_url" value="{{ old('base_url', $connection->base_url ?? '') }}"
                           placeholder="https://cloud.example.com/remote.php/dav/files/svc/WorkDiary" class="input input-bordered input-sm" required>
                    <span class="label-text-alt text-muted">{{ __('webdav.field.base_url_help') }}</span>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('webdav.field.username') }}</span>
                    <input type="text" name="username" value="{{ old('username', $connection->username ?? '') }}"
                           autocomplete="off" class="input input-bordered input-sm" required>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('webdav.field.app_password') }}</span>
                    <input type="password" name="app_password" autocomplete="new-password"
                           placeholder="{{ $connection ? __('webdav.field.password_keep') : '' }}"
                           class="input input-bordered input-sm" @required(! $connection)>
                    <span class="label-text-alt text-muted">{{ __('webdav.field.password_help') }}</span>
                </label>
                <label class="form-control">
                    <span class="label-text">{{ __('webdav.field.default_folder') }}</span>
                    <input type="text" name="default_folder" value="{{ old('default_folder', $connection->default_folder ?? 'Dokumente') }}"
                           class="input input-bordered input-sm" required>
                </label>
                <label class="form-control justify-end">
                    <span class="label cursor-pointer justify-start gap-2">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" class="toggle toggle-sm toggle-primary"
                               @checked(old('active', $connection->active ?? true))>
                        <span class="label-text">{{ __('webdav.field.active') }}</span>
                    </span>
                </label>
            </div>

            {{-- Spiegel-Quellen (Rang 19): Dokumente / Rechnungen / Protokolle. --}}
            @php $currentSources = (array) old('sources', $connection->sources ?? ['document']); @endphp
            <div class="form-control">
                <span class="label-text">{{ __('webdav.field.sources') }}</span>
                <div class="flex flex-wrap gap-4 pt-1">
                    @foreach (\App\Models\WebdavConnection::SOURCES as $source)
                        <label class="label cursor-pointer justify-start gap-2">
                            <input type="checkbox" name="sources[]" value="{{ $source }}" class="checkbox checkbox-sm"
                                   @checked(in_array($source, $currentSources, true))>
                            <span class="label-text">{{ __('webdav.field.source_' . $source) }}</span>
                        </label>
                    @endforeach
                </div>
                <span class="label-text-alt text-muted">{{ __('webdav.field.sources_help') }}</span>
            </div>

            {{-- Dokumenttyp → Ordner --}}
            <div>
                <h3 class="mb-1 text-sm font-semibold">{{ __('webdav.folder.heading') }}</h3>
                <p class="mb-2 text-xs text-muted">{{ __('webdav.folder.help') }}</p>
                <div class="space-y-2">
                    @php $map = $connection->folder_map ?? []; @endphp
                    @foreach (array_merge(array_keys($map), array_fill(0, 3, '')) as $mapType)
                        <div class="flex flex-wrap items-center gap-2">
                            <select name="folder_type[]" class="select select-bordered select-sm w-56">
                                <option value="">{{ __('webdav.folder.type_placeholder') }}</option>
                                @foreach ($documentTypes as $type)
                                    <option value="{{ $type->value }}" @selected($mapType === $type->value)>{{ $type->value }}</option>
                                @endforeach
                            </select>
                            <span class="text-muted">→</span>
                            <input type="text" name="folder_path[]" value="{{ $mapType !== '' ? ($map[$mapType] ?? '') : '' }}"
                                   placeholder="{{ __('webdav.folder.path_placeholder') }}" class="input input-bordered input-sm w-64">
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('webdav.action.save') }}</button>
            </div>
        </form>
    </div>
</x-page-shell>
@endsection
