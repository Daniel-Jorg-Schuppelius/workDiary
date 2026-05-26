@extends('layouts.app')
@section('title', __('Veranstaltungs-Kategorien'))
@section('nav-title', __('Veranstaltungs-Kategorien'))

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $categories */
@endphp

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('Kategorien für Veranstaltungen und Termine pflegen.')">
                <x-slot:actions>
                    @can('create', App\Models\EventCategory::class)
                        <x-icon-btn icon="add" tone="primary" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('event-categories.create').'?dialog=1'"
                                    show-label>{{ __('Neue Kategorie') }}</x-icon-btn>
                    @endcan
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <x-table :pinRows="true" :zebra="true">
            <thead class="bg-base-200">
                <tr>
                    <th data-sort data-sort-default="asc">{{ __('Name') }}</th>
                    <th>{{ __('Beschreibung') }}</th>
                    <th data-sort>{{ __('Zertifikat') }}</th>
                    <th class="text-right" data-sort data-sort-type="number">{{ __('Gültig (Monate)') }}</th>
                    <th>{{ __('Reminder (Min.)') }}</th>
                    <th data-sort>{{ __('Status') }}</th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $cat)
                    <tr class="hover">
                        <td>
                            <div class="flex items-center gap-2 font-semibold">
                                <span class="inline-block w-3 h-3 rounded" style="background:{{ $cat->color ?? '#999' }}"></span>
                                {{ $cat->name }}
                            </div>
                        </td>
                        <td class="max-w-md truncate text-base-content/70 text-xs">{{ $cat->description }}</td>
                        <td>
                            @if ($cat->requires_certificate)
                                <span class="inline-flex items-center gap-1 text-success">
                                    <x-icon name="check_circle" /> {{ __('Erforderlich') }}
                                </span>
                            @else
                                <span class="opacity-50">—</span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $cat->certificate_valid_months ?? '—' }}</td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @foreach (($cat->reminder_offsets ?? []) as $off)
                                    <span class="badge badge-ghost badge-sm font-mono">{{ $off }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            @if ($cat->is_active)
                                <span class="badge badge-success badge-sm">{{ __('Aktiv') }}</span>
                            @else
                                <span class="badge badge-ghost badge-sm">{{ __('Inaktiv') }}</span>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @can('update', $cat)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('event-categories.edit', $cat).'?dialog=1'"
                                            :label="__('Bearbeiten')" />
                            @endcan
                            @can('delete', $cat)
                                <form action="{{ route('event-categories.destroy', $cat) }}" method="POST" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Kategorie wirklich löschen?') }}"
                                      data-confirm-label="{{ __('Löschen') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="7"
                        icon='<span class="material-symbols-outlined" aria-hidden="true">category</span>'
                        :title="__('Noch keine Kategorien angelegt')" compact />
                @endforelse
            </tbody>
        </x-table>

        @if ($categories->hasPages())
            <div class="flex-none">{{ $categories->links() }}</div>
        @endif
    </x-page-shell>
@endsection
