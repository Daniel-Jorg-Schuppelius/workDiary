@extends('layouts.app')
@section('title', __('Lesezeichen'))
@section('nav-title', __('Lesezeichen'))
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
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

        <x-table scroll="flex" :pinRows="true" :zebra="true">
            <thead class="bg-base-200">
                <tr>
                    <th class="w-16 text-right">{{ __('#') }}</th>
                    <th class="w-12"></th>
                    <th>{{ __('Bezeichnung') }}</th>
                    <th>{{ __('URL') }}</th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </thead>
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
                            <form method="POST" action="{{ route('bookmarks.destroy', $bookmark) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-title="{{ __('Lesezeichen löschen') }}"
                                  data-confirm-message="{{ __('Das Lesezeichen wird entfernt.') }}"
                                  data-confirm-label="{{ __('Löschen') }}">
                                @csrf @method('DELETE')
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </form>
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
