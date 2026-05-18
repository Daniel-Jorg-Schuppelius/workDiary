@extends('layouts.app')
@section('title', __('Schichttypen'))
@section('nav-title', __('Schichttypen'))

@section('content')
    <x-page-shell gap="6">
        <x-slot:toolbar>
            <x-page-toolbar>
                <x-slot:actions>
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('shift-types.create')"
                                show-label>{{ __('Neuer Schichttyp') }}</x-icon-btn>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <x-table :pinRows="true" :zebra="true">
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
                                    <span class="badge badge-success badge-sm">{{ __('Aktiv') }}</span>
                                @else
                                    <span class="badge badge-ghost badge-sm">{{ __('Inaktiv') }}</span>
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
    </x-page-shell>
@endsection
