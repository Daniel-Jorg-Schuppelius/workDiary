{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('Lesezeichen'))
@section('nav-title', __('Lesezeichen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\UserBookmark> $bookmarks */
@endphp

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Verwalte deine persönlichen Lesezeichen für schnellen Zugriff.')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('bookmarks.create')"
                        show-label>{{ __('Neues Lesezeichen') }}</x-icon-btn>
        </x-slot:actions>

        <x-filter-bar :action="route('bookmarks.index')" :reset="route('bookmarks.index')">
            <input type="text" name="q" value="{{ $search ?? '' }}"
                   class="input input-sm input-bordered w-48 shrink-0"
                   placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
        </x-filter-bar>

        <x-table scroll="flex" :pinRows="true" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="number" align="right" class="w-16">{{ __('#') }}</x-table.th>
                    <th class="w-12"></th>
                    <x-table.th sort type="string">{{ __('Bezeichnung') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('URL') }}</x-table.th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            <tbody>
                @forelse ($bookmarks as $bookmark)
                    <tr class="hover">
                        <td class="text-right tabular-nums">{{ $bookmark->sort_order }}</td>
                        <td>
                            <span class="material-symbols-outlined" aria-hidden="true">{{ $bookmark->icon ?: 'bookmark' }}</span>
                        </td>
                        <td class="font-semibold">{{ $bookmark->label }}</td>
                        <td class="truncate max-w-md">
                            <a href="{{ $bookmark->url }}" class="link link-hover text-sm">{{ $bookmark->url }}</a>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <x-icon-btn icon="edit"
                                        data-entry-modal-trigger
                                        :href="route('bookmarks.edit', $bookmark)"
                                        :label="__('Bearbeiten')" />
                            <x-action-form :action="route('bookmarks.destroy', $bookmark)"
                                  method="DELETE"
                                  data-confirm-title="{{ __('Lesezeichen löschen') }}"
                                  :confirm="__('Das Lesezeichen wird entfernt.')"
                                  :confirm-label="__('Löschen')">
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </x-action-form>
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5"
                        icon='<span class="material-symbols-outlined" aria-hidden="true">bookmark</span>'
                        :title="__('Noch keine Lesezeichen angelegt')" compact />
                @endforelse
            </tbody>
        </x-table>
    </x-index-page>
@endsection
