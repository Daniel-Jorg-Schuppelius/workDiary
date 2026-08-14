{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Tags') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Tags'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Tags zur Klassifikation von Auftragsbuch-Einträgen verwalten.')">
    <x-slot:actions>
        @can('create', App\Models\Tag::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('tags.create')"
                        show-label>{{ __('Neuer Tag') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

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
                                <x-action-form :action="route('tags.destroy', $tag)" method="DELETE"
                                      :confirm="__('Tag wird inklusive aller Verknüpfungen entfernt.')"
                                      data-confirm-title="{{ __('Tag löschen') }}"
                                      :confirm-label="__('Löschen')">
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </x-action-form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">label</span>' :colspan="5" :title="__('Noch keine Tags angelegt')" compact />
                @endforelse
    </x-table>

    <x-pagination :paginator="$tags" standing />
</x-index-page>
@endsection
