@extends('layouts.app')
@section('title', __('Tags') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Tags'))

@section('content')
<div class="flex h-full min-h-0 w-full flex-col gap-6 overflow-auto">
    {{-- Neuer Tag (Admin) --}}    @can('create', App\Models\Tag::class)
        @if (auth()->user()->isAdmin())
            <div class="flex justify-end">
                <a href="{{ route('tags.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary">{{ __('+ Neuer Tag') }}</a>
            </div>
        @endif
    @endcan

    {{-- Tag-Liste --}}
    <x-table>
            <thead>
                <tr>
                    <th>{{ __('Tag') }}</th>
                    <th class="text-right">{{ __('Tagebuch') }}</th>
                    <th class="text-right">{{ __('Bereitschaft') }}</th>
                    <th class="text-right">{{ __('Notdienst') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tags as $tag)
                    <tr>
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
                                <a href="{{ route('tags.edit', $tag) }}" data-entry-modal-trigger class="btn btn-ghost btn-xs">{{ __('Bearbeiten') }}</a>
                            @endcan
                            @can('delete', $tag)
                                <form method="POST" action="{{ route('tags.destroy', $tag) }}" class="inline"
                                    data-confirm-dialog
                                    data-confirm-title="{{ __('Tag löschen') }}"
                                    data-confirm-message="{{ __('Tag wird inklusive aller Verknüpfungen entfernt.') }}"
                                    data-confirm-label="{{ __('Löschen') }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-xs text-error">{{ __('Löschen') }}</button>
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
