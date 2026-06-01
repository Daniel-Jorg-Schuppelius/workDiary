@extends('layouts.app')
@section('title', __('Schichttypen'))
@section('nav-title', __('Schichttypen'))
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Schichttypen für Dienstpläne und Stempelungen verwalten.')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('shift-types.create')"
                        show-label>{{ __('Neuer Schichttyp') }}</x-icon-btn>
        </x-slot:actions>

        <x-table scroll="flex" :pinRows="true" :zebra="true">
                <thead class="bg-base-200">
                    <tr>
                        <th data-sort data-sort-default="asc">{{ __('Name') }}</th>
                        <th data-sort>{{ __('Kürzel') }}</th>
                        <th data-sort>{{ __('Standardzeit') }}</th>
                        <th data-sort>{{ __('Status') }}</th>
                        <th class="text-right" data-sort data-sort-type="number">{{ __('Verwendet') }}</th>
                        <th class="w-32 text-right">{{ __('Aktion') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr class="hover">
                            <td class="flex items-center gap-2">
                                <span class="inline-block w-3 h-3 rounded" style="background:{{ $type->color }}"></span>
                                {{ $type->name }}
                            </td>
                            <td><span class="font-mono">{{ $type->abbreviation }}</span></td>
                            <td class="whitespace-nowrap">
                                @if ($type->default_start_time && $type->default_end_time)
                                    {{ $type->default_start_time }}–{{ $type->default_end_time }}
                                @else — @endif
                            </td>
                            <td>
                                @if ($type->is_active)
                                    <x-status-badge tone="success" size="sm">{{ __('Aktiv') }}</x-status-badge>
                                @else
                                    <x-status-badge tone="ghost" size="sm">{{ __('Inaktiv') }}</x-status-badge>
                                @endif
                            </td>
                            <td class="text-right">{{ $type->scheduled_shifts_count }}</td>
                            <td class="text-right whitespace-nowrap">
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('shift-types.edit', $type)"
                                            :label="__('Bearbeiten')" />
                                <form action="{{ route('shift-types.destroy', $type) }}" method="POST" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Wirklich löschen?') }}"
                                      data-confirm-label="{{ __('Löschen') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </form>
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">work_history</span>' :colspan="6" :title="__('Keine Einträge')" compact />
                    @endforelse
                </tbody>
        </x-table>
    </x-index-page>
@endsection
