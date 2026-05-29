@extends('layouts.app')
@section('title', __('Tags') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Tags'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Tags zur Klassifikation von Auftragsbuch-Einträgen verwalten.')">
            <x-slot:actions>
                @can('create', App\Models\Tag::class)
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('tags.create')"
                                show-label>{{ __('Neuer Tag') }}</x-icon-btn>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Tag-Liste --}}
    <x-table :pinRows="true" scroll="flex"
             table-sort="server"
             :route="route('tags.index')"
             :current-sort="$sort ?? null"
             :current-dir="$dir ?? 'asc'">
            <x-slot:head>
                <tr>
                    <x-table.th sort="name" default>{{ __('Tag') }}</x-table.th>
                    <x-table.th sort="diary" align="right">{{ __('Auftragsbuch') }}</x-table.th>
                    <x-table.th sort="shifts" align="right">{{ __('Bereitschaft') }}</x-table.th>
                    <x-table.th sort="assignments" align="right">{{ __('Notdienst') }}</x-table.th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
                @forelse ($tags as $tag)
                    <tr class="hover">
                        <td>
                            <x-status-badge size="md" outline
                                  :style="$tag->color ? 'border-color: '.$tag->color.'; color: '.$tag->color.';' : null">
                                #{{ $tag->name }}
                            </x-status-badge>
                        </td>
                        <td class="text-right tabular-nums">{{ $tag->diary_entries_count }}</td>
                        <td class="text-right tabular-nums">{{ $tag->shifts_count }}</td>
                        <td class="text-right tabular-nums">{{ $tag->assignments_count }}</td>
                        <td class="text-right whitespace-nowrap">
                            @can('update', $tag)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('tags.edit', $tag)"
                                            :label="__('Bearbeiten')" />
                            @endcan
                            @can('delete', $tag)
                                <form method="POST" action="{{ route('tags.destroy', $tag) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-title="{{ __('Tag löschen') }}"
                                      data-confirm-message="{{ __('Tag wird inklusive aller Verknüpfungen entfernt.') }}"
                                      data-confirm-label="{{ __('Löschen') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">label</span>' :colspan="5" :title="__('Noch keine Tags angelegt')" compact />
                @endforelse
    </x-table>

    @if ($tags->hasPages())
        <div class="flex-none">{{ $tags->links() }}</div>
    @endif
</x-page-shell>
@endsection
