{{--
  Created on   : Tue Jul 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('cloud_intake.title.index') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('cloud_intake.title.index'))

@section('content')
<x-index-page :subtitle="__('cloud_intake.title.subtitle')">
    <x-slot:actions>
        @if ($canManage ?? false)
            <form method="POST" action="{{ route('admin.cloud-intake.dropbox.oauth.start') }}" class="leading-none">
                @csrf
                <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('cloud_intake.action.connect_dropbox') }}</x-icon-btn>
            </form>
            <form method="POST" action="{{ route('admin.cloud-intake.microsoft.oauth.start') }}" class="leading-none">
                @csrf
                <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('cloud_intake.action.connect_microsoft') }}</x-icon-btn>
            </form>
            <form method="POST" action="{{ route('admin.cloud-intake.google.oauth.start') }}" class="leading-none">
                @csrf
                <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('cloud_intake.action.connect_google') }}</x-icon-btn>
            </form>
        @endif
    </x-slot:actions>

    @if (session('success'))
        <div role="alert" class="alert alert-success"><span>{{ session('success') }}</span></div>
    @endif
    @if (session('error'))
        <div role="alert" class="alert alert-warning"><span>{{ session('error') }}</span></div>
    @endif

    @if ($connections->isEmpty())
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">cloud_download</span>'
                       :title="__('cloud_intake.title.empty')" />
    @else
        @foreach ($connections as $connection)
            @php /** @var \App\Models\CloudIntake\CloudDocumentConnection $connection */ @endphp
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="card-title text-base">{{ $connection->provider->label() }} — {{ $connection->name }}</h3>
                        <x-status-badge size="xs" :tone="$connection->status->tone()">{{ $connection->status->label() }}</x-status-badge>
                        <span class="text-sm text-base-content/60">{{ $connection->external_account_label ?? __('cloud_intake.field.account_unconfirmed') }}</span>
                        <div class="ml-auto flex items-center gap-1.5">
                            <form method="POST" action="{{ route('admin.cloud-intake.preview', $connection) }}" class="leading-none">
                                @csrf
                                <x-icon-btn icon="preview" tone="ghost" size="xs" type="submit" show-label>{{ __('cloud_intake.action.preview') }}</x-icon-btn>
                            </form>
                            @if ($canManage ?? false)
                                <x-action-form :action="route('admin.cloud-intake.disconnect', $connection)"
                                      method="DELETE"
                                      :confirm="__('cloud_intake.action.disconnect_confirm')"
                                      :confirm-label="__('cloud_intake.action.disconnect')">
                                    <x-icon-btn icon="link_off" tone="error" size="xs" type="submit" :label="__('cloud_intake.action.disconnect')" />
                                </x-action-form>
                            @endif
                        </div>
                    </div>

                    @if ($connection->last_error)
                        <div role="alert" class="alert alert-warning text-sm">
                            <x-icon name="warning" />
                            <span>{{ $connection->last_error }} <span class="text-base-content/60">({{ $connection->last_error_at?->ftime() }})</span></span>
                        </div>
                    @endif

                    {{-- Container + Stammordner (Konzept §Verbindung und Preflight) --}}
                    @if ($canManage ?? false)
                        <form method="POST" action="{{ route('admin.cloud-intake.folder', $connection) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <div class="fieldset">
                                <label class="fieldset-label" for="ci-container-{{ $connection->id }}">{{ __('cloud_intake.field.container') }}</label>
                                <input id="ci-container-{{ $connection->id }}" type="text" name="container_id" required maxlength="512"
                                       value="{{ old('container_id', $connection->container_id) }}"
                                       class="input input-sm input-bordered font-mono w-52">
                            </div>
                            <div class="fieldset">
                                <label class="fieldset-label" for="ci-rootid-{{ $connection->id }}">{{ __('cloud_intake.field.root_folder_id') }}</label>
                                <input id="ci-rootid-{{ $connection->id }}" type="text" name="root_folder_id" maxlength="512"
                                       value="{{ old('root_folder_id', $connection->root_folder_id) }}"
                                       class="input input-sm input-bordered font-mono w-52">
                            </div>
                            <div class="fieldset flex-1 min-w-60">
                                <label class="fieldset-label" for="ci-rootpath-{{ $connection->id }}">{{ __('cloud_intake.field.root_folder') }}</label>
                                <input id="ci-rootpath-{{ $connection->id }}" type="text" name="root_folder_path" required maxlength="1024"
                                       value="{{ old('root_folder_path', $connection->root_folder_path) }}"
                                       class="input input-sm input-bordered font-mono w-full" placeholder="/WorkDiary">
                            </div>
                            <x-icon-btn icon="save" tone="primary" size="sm" type="submit" show-label>{{ __('cloud_intake.action.save_folder') }}</x-icon-btn>
                        </form>
                    @endif

                    {{-- Ordnerregeln --}}
                    <div class="flex items-center gap-2">
                        <h4 class="font-semibold text-sm">{{ __('cloud_intake.route.heading') }}</h4>
                        @if ($canManageRoutes ?? false)
                            <x-icon-btn icon="add" tone="ghost" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('admin.cloud-intake.routes.create', $connection)"
                                        show-label>{{ __('cloud_intake.route.create') }}</x-icon-btn>
                        @endif
                    </div>
                    @if ($connection->routes->isEmpty())
                        <p class="text-sm text-base-content/60">{{ __('cloud_intake.route.empty') }}</p>
                    @else
                        <x-table>
                            <x-slot:head>
                                <tr>
                                    <th class="text-right">{{ __('cloud_intake.route.priority') }}</th>
                                    <th>{{ __('cloud_intake.route.pattern') }}</th>
                                    <th>{{ __('cloud_intake.route.target') }}</th>
                                    <th>{{ __('cloud_intake.route.active') }}</th>
                                    <th></th>
                                </tr>
                            </x-slot:head>
                            @foreach ($connection->routes as $route)
                                <tr>
                                    <td class="text-right tabular-nums">{{ $route->priority }}</td>
                                    <td class="font-mono text-sm">{{ $route->path_pattern }}</td>
                                    <td>{{ $route->target->label() }}</td>
                                    <td>
                                        <x-status-badge size="xs" :tone="$route->active ? 'success' : 'ghost'">
                                            {{ $route->active ? __('cloud_intake.route.active') : __('cloud_intake.route.inactive') }}
                                        </x-status-badge>
                                    </td>
                                    <td class="text-right">
                                        @if ($canManageRoutes ?? false)
                                            <x-icon-btn icon="edit" tone="ghost" size="xs"
                                                        data-entry-modal-trigger
                                                        :href="route('admin.cloud-intake.routes.edit', $route)"
                                                        :label="__('cloud_intake.route.edit')" />
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-table>
                    @endif
                </div>
            </div>
        @endforeach

        {{-- Importprotokoll (Übergabenachweise) --}}
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h3 class="card-title text-base">{{ __('cloud_intake.log.heading') }}</h3>
                @if ($items->total() === 0)
                    <p class="text-sm text-base-content/60">{{ __('cloud_intake.log.empty') }}</p>
                @else
                    <x-table>
                        <x-slot:head>
                            <tr>
                                <th>{{ __('cloud_intake.field.provider') }}</th>
                                <th>{{ __('cloud_intake.log.path') }}</th>
                                <th>{{ __('cloud_intake.log.revision') }}</th>
                                <th>{{ __('cloud_intake.field.status') }}</th>
                                <th>{{ __('cloud_intake.log.reason') }}</th>
                                <th>{{ __('cloud_intake.log.when') }}</th>
                            </tr>
                        </x-slot:head>
                        @foreach ($items as $item)
                            @php /** @var \App\Models\CloudIntake\CloudDocumentItem $item */ @endphp
                            <tr>
                                <td>{{ $item->provider->label() }}</td>
                                <td class="font-mono text-sm">{{ $item->source_path }}</td>
                                <td class="font-mono text-xs">{{ $item->revision }}</td>
                                <td><x-status-badge size="xs" :tone="$item->status->tone()">{{ $item->status->label() }}</x-status-badge></td>
                                <td class="text-sm text-base-content/60">{{ $item->status_reason ?? '—' }}</td>
                                <td class="text-sm tabular-nums">{{ $item->created_at?->ftime() }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                    <x-pagination :paginator="$items" />
                @endif
            </div>
        </div>
    @endif
</x-index-page>
@endsection
