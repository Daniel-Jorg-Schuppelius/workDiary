@extends('layouts.app')
@section('title', __('ideas.title.index') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('ideas.title.index'))

@section('content')
<x-index-page :subtitle="__('ideas.subtitle')">
    <x-slot:actions>
        @can('create', \App\Models\IdeaMap::class)
            <button type="button" class="btn btn-sm btn-ghost gap-1"
                    data-open-dialog="ideas-import-dialog">
                <span class="material-symbols-outlined text-base" aria-hidden="true">upload_file</span>
                {{ __('ideas.import.action') }}
            </button>
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('ideas.create')" show-label>{{ __('ideas.action.create') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    @error('file')
        <div class="alert alert-error mb-4" role="alert">
            <span class="material-symbols-outlined" aria-hidden="true">error</span>
            <span>{{ $message }}</span>
        </div>
    @enderror

    <div class="flex flex-wrap items-center gap-2 mb-4">
        @foreach (['active', 'archived', 'trashed'] as $f)
            <a href="{{ route('ideas.index', ['filter' => $f]) }}"
               @class(['btn btn-xs', 'btn-primary' => $filter === $f, 'btn-ghost' => $filter !== $f])>
                {{ __('ideas.filter.' . $f) }}
            </a>
        @endforeach
    </div>

    @if ($maps->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">emoji_objects</span>'
                       :title="__('ideas.empty')" />
    @else
        <x-card padding="p-0">
            <x-table>
                <x-slot:head>
                    <th>{{ __('ideas.col.title') }}</th>
                    <th>{{ __('ideas.col.owner') }}</th>
                    <th>{{ __('ideas.col.visibility') }}</th>
                    <th class="text-right">{{ __('ideas.col.nodes') }}</th>
                    <th>{{ __('ideas.col.updated') }}</th>
                    <th class="text-right">{{ __('ideas.col.actions') }}</th>
                </x-slot:head>
                @foreach ($maps as $map)
                    <tr>
                        <td>
                            @if ($filter === 'trashed')
                                {{ $map->title }}
                            @else
                                <a href="{{ route('ideas.show', $map) }}" class="link font-medium">{{ $map->title }}</a>
                            @endif
                            @if ($map->description)
                                <div class="text-xs opacity-60">{{ \Illuminate\Support\Str::limit($map->description, 80) }}</div>
                            @endif
                        </td>
                        <td class="text-sm">{{ $map->owner?->name ?: '—' }}</td>
                        <td><span class="badge badge-sm">{{ $map->visibility->label() }}</span></td>
                        <td class="text-right tabular-nums">{{ $map->nodes_count }}</td>
                        <td class="text-sm">{{ $map->updated_at?->format('d.m.Y H:i') }}</td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                @if ($filter === 'trashed')
                                    <form method="POST" action="{{ route('ideas.restore', ['mapSqid' => $map->sqid]) }}">@csrf
                                        <x-icon-btn icon="restore_from_trash" size="xs" tone="success" type="submit" :title="__('ideas.action.restore')" />
                                    </form>
                                @elseif ($map->isArchived())
                                    @can('delete', $map)
                                        <form method="POST" action="{{ route('ideas.unarchive', $map) }}">@csrf
                                            <x-icon-btn icon="unarchive" size="xs" type="submit" :title="__('ideas.action.unarchive')" />
                                        </form>
                                    @endcan
                                @else
                                    @can('update', $map)
                                        <x-icon-btn icon="edit" size="xs" data-entry-modal-trigger
                                                    :href="route('ideas.edit', $map)" :title="__('Bearbeiten')" />
                                    @endcan
                                    @can('delete', $map)
                                        <form method="POST" action="{{ route('ideas.archive', $map) }}">@csrf
                                            <x-icon-btn icon="archive" size="xs" type="submit" :title="__('ideas.action.archive')" />
                                        </form>
                                        <form method="POST" action="{{ route('ideas.destroy', $map) }}"
                                              data-confirm-dialog data-confirm-message="{{ __('ideas.confirm_delete') }}">
                                            @csrf @method('DELETE')
                                            <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Löschen')" />
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
        <x-pagination :paginator="$maps" standing />
    @endif
</x-index-page>

@can('create', \App\Models\IdeaMap::class)
    {{-- Import FreeMind/OPML (MVP-138): eigener Upload-Dialog, XXE-gehärtet serverseitig --}}
    <x-modal id="ideas-import-dialog" :embedded="false" icon="upload_file"
             :eyebrow="__('ideas.title.index')" :title="__('ideas.import.title')"
             :action="route('ideas.import')" method="POST" enctype="multipart/form-data"
             :submit-label="__('ideas.import.submit')">
        <p class="text-sm opacity-70">{{ __('ideas.import.hint') }}</p>
        <label class="fieldset">
            <span class="fieldset-label">{{ __('ideas.import.file') }}</span>
            <input type="file" name="file" accept=".mm,.opml,.xml" required
                   class="file-input file-input-sm file-input-bordered w-full">
        </label>
    </x-modal>
@endcan
@endsection
