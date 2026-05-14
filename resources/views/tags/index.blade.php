@extends('layouts.app')
@section('title', __('Tags') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Tags'))

@section('content')
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">
    <div class="flex justify-end">
        @can('create', App\Models\Tag::class)
            @if (auth()->user()->isAdmin())
                <a href="{{ route('tags.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary">{{ __('+ Neuer Tag') }}</a>
            @endif
        @endcan
    </div>

    {{-- Tag-Liste --}}
    <x-table :pin-rows="true" scroll="flex">
            <thead class="bg-base-200">
                <tr>
                    <th><x-sort-th column="name" :route="route('tags.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'" default="name">{{ __('Tag') }}</x-sort-th></th>
                    <th class="text-right"><x-sort-th column="diary" :route="route('tags.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('Tagebuch') }}</x-sort-th></th>
                    <th class="text-right"><x-sort-th column="shifts" :route="route('tags.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('Bereitschaft') }}</x-sort-th></th>
                    <th class="text-right"><x-sort-th column="assignments" :route="route('tags.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('Notdienst') }}</x-sort-th></th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tags as $tag)
                    <tr class="hover">
                        <td>
                            <span class="badge badge-outline" @if ($tag->color) style="border-color: {{ $tag->color }}; color: {{ $tag->color }};" @endif>
                                #{{ $tag->name }}
                            </span>
                        </td>
                        <td class="text-right">{{ $tag->diary_entries_count }}</td>
                        <td class="text-right">{{ $tag->shifts_count }}</td>
                        <td class="text-right">{{ $tag->assignments_count }}</td>
                        <td class="text-right">
                            @can('update', $tag)
                                <a href="{{ route('tags.edit', $tag) }}" data-entry-modal-trigger class="btn btn-xs btn-ghost" title="{{ __('Bearbeiten') }}" aria-label="{{ __('Bearbeiten') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                            @endcan
                            @can('delete', $tag)
                                <form method="POST" action="{{ route('tags.destroy', $tag) }}" class="inline"
                                    data-confirm-dialog
                                    data-confirm-title="{{ __('Tag löschen') }}"
                                    data-confirm-message="{{ __('Tag wird inklusive aller Verknüpfungen entfernt.') }}"
                                    data-confirm-label="{{ __('Löschen') }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-ghost text-error" title="{{ __('Löschen') }}" aria-label="{{ __('Löschen') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-base-content/60 py-6">{{ __('Noch keine Tags angelegt.') }}</td></tr>
                @endforelse
            </tbody>
    </x-table>

    @if ($tags->hasPages())
        <div>{{ $tags->links('pagination::simple-tailwind') }}</div>
    @endif
</div>
@endsection
