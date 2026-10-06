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
@include('partials.page-fill')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\Platform\UserBookmark> $bookmarks */
@endphp

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Verwalten Sie Ihre persönlichen Lesezeichen für schnellen Zugriff.')">
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

        <x-table scroll="flex" :pinRows="true" :zebra="true" table-sort="server"
                 :route="route('bookmarks.index')"
                 :current-sort="$sort"
                 :current-dir="$dir"
                 :sort-params="request()->except(['sort', 'dir', 'page'])">
            <x-slot:head>
                <tr>
                    <x-table.th sort="sort_order" default align="right" class="w-16">{{ __('#') }}</x-table.th>
                    <th class="w-12"></th>
                    <x-table.th sort="label">{{ __('Bezeichnung') }}</x-table.th>
                    <x-table.th sort="url">{{ __('URL') }}</x-table.th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
                @forelse ($bookmarks as $bookmark)
                    <tr class="hover">
                        <td class="text-right tabular-nums">{{ $bookmark->sort_order }}</td>
                        <td>
                            <x-icon name="{{ $bookmark->icon ?: 'bookmark' }}" />
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
                        icon="bookmark"
                        :title="__('Noch keine Lesezeichen angelegt')" compact />
                @endforelse
        </x-table>

        <x-pagination :paginator="$bookmarks" standing />
    </x-index-page>
@endsection
