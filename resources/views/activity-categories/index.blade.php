@extends('layouts.app')
@section('title', __('Tätigkeiten'))
@section('nav-title', __('Tätigkeiten'))
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $categories */
@endphp

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Verwaltet die Kategorien für nicht-projektgebundene Arbeitszeit.')">
        <x-slot:actions>
            @can('create', App\Models\ActivityCategory::class)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('activity-categories.create').'?dialog=1'"
                            show-label>{{ __('Neue Tätigkeit') }}</x-icon-btn>
            @endcan
        </x-slot:actions>

        <x-table scroll="flex" :pinRows="true" :zebra="true">
            <thead class="bg-base-200">
                <tr>
                    <th data-sort data-sort-default="asc" data-sort-type="number" class="w-16 text-right">{{ __('#') }}</th>
                    <th data-sort>{{ __('Schlüssel') }}</th>
                    <th data-sort>{{ __('Bezeichnung') }}</th>
                    <th data-sort>{{ __('Typ') }}</th>
                    <th data-sort class="text-center">{{ __('Arbeit') }}</th>
                    <th data-sort class="text-center">{{ __('Abrechenbar') }}</th>
                    <th data-sort>{{ __('Status') }}</th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $cat)
                    <tr class="hover">
                        <td class="text-right tabular-nums">{{ $cat->sort_order }}</td>
                        <td>
                            <div class="flex items-center gap-2 font-mono text-xs">
                                <span class="inline-block w-3 h-3 rounded" style="background:{{ $cat->color ?? '#999' }}"></span>
                                {{ $cat->key }}
                            </div>
                        </td>
                        <td class="font-semibold">
                            <div class="flex items-center gap-2">
                                @if ($cat->icon)
                                    <span class="material-symbols-outlined text-base opacity-70" aria-hidden="true">{{ $cat->icon }}</span>
                                @endif
                                <span>{{ $cat->label }}</span>
                            </div>
                            @if ($cat->description)
                                <div class="text-xs text-base-content/60 truncate max-w-md">{{ $cat->description }}</div>
                            @endif
                        </td>
                        <td>
                            <x-status-badge tone="ghost">{{ $cat->activity_type->label() }}</x-status-badge>
                        </td>
                        <td class="text-center">
                            @if ($cat->counts_as_work)
                                <x-status-badge tone="success">{{ __('Ja') }}</x-status-badge>
                            @else
                                <x-status-badge tone="ghost">{{ __('Nein') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($cat->billable_default)
                                <x-status-badge tone="info">{{ __('Ja') }}</x-status-badge>
                            @else
                                <x-status-badge tone="ghost">{{ __('Nein') }}</x-status-badge>
                            @endif
                        </td>
                        <td>
                            @if ($cat->active)
                                <x-status-badge tone="success">{{ __('Aktiv') }}</x-status-badge>
                            @else
                                <x-status-badge tone="ghost">{{ __('Inaktiv') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @can('update', $cat)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('activity-categories.edit', $cat).'?dialog=1'"
                                            :label="__('Bearbeiten')" />
                            @endcan
                            @can('delete', $cat)
                                <form method="POST" action="{{ route('activity-categories.destroy', $cat) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-title="{{ __('Tätigkeit löschen') }}"
                                      data-confirm-message="{{ __('Tätigkeit wird endgültig entfernt. Bestehende Zeiteinträge bleiben erhalten.') }}"
                                      data-confirm-label="{{ __('Löschen') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="8"
                        icon='<span class="material-symbols-outlined" aria-hidden="true">category</span>'
                        :title="__('Noch keine Tätigkeiten angelegt')" compact />
                @endforelse
            </tbody>
        </x-table>

        <x-pagination :paginator="$categories" />
    </x-index-page>
@endsection
