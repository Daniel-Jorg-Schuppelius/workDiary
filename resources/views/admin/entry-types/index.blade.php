@extends('layouts.app')

@section('title', __('Eintragstypen'))
@section('nav-title', __('Eintragstypen'))
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Auftragstypen und ihre Pflichtklassifikationen pro Mandant verwalten.')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('admin.entry-types.create')"
                    show-label>{{ __('Eintragstyp anlegen') }}</x-icon-btn>
    </x-slot:actions>

    <div role="alert" class="alert alert-info alert-soft">
        <x-icon name="info" />
        <div>
            <h3 class="font-semibold">{{ __('Was sind Eintragstypen?') }}</h3>
            <div class="text-sm">
                {{ __('Eintragstypen kategorisieren Einträge im Auftragsbuch (z. B. „Einsatz“, „Termin“, „Notiz“) und steuern, welche Felder beim Anlegen sichtbar bzw. verpflichtend sind – etwa ob ein Kunde, eine Adresse, ein Zeitfenster oder eine Tour zugeordnet werden muss. Zusätzlich legen sie Standardwerte (Status, Priorität, Servicedauer) sowie Symbol und Farbe für die Darstellung in Listen und Kalenderansichten fest.') }}
            </div>
        </div>
    </div>

    <x-table scroll="flex" :pinRows="true" table-sort="server"
             :route="route('admin.entry-types.index')"
             :current-sort="$sort ?? null"
             :current-dir="$dir ?? 'asc'">
        <x-slot:head>
            <tr>
                <x-table.th sort="sort" default class="w-12">#</x-table.th>
                <x-table.th sort="label">{{ __('Bezeichnung') }}</x-table.th>
                <x-table.th sort="slug">{{ __('Slug') }}</x-table.th>
                <th>{{ __('Flags') }}</th>
                <x-table.th sort="entries" align="center">{{ __('Einträge') }}</x-table.th>
                <x-table.th sort="is_active" align="center">{{ __('Aktiv') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
            @forelse ($entryTypes as $type)
                <tr>
                    <td class="text-base-content/60">{{ $type->sort }}</td>
                    <td class="font-medium">
                        <span class="inline-flex items-center gap-2">
                            <x-icon :name="$type->icon ?: 'task_alt'" class="text-{{ $type->color ?: 'primary' }}" />
                            {{ $type->label }}
                        </span>
                        @if ($type->description)
                            <div class="text-xs text-base-content/60">{{ $type->description }}</div>
                        @endif
                    </td>
                    <td class="font-mono text-sm text-base-content/60">{{ $type->slug }}</td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @if ($type->requires_customer) <x-status-badge size="xs" tone="info">{{ __('Kunde') }}</x-status-badge> @endif
                            @if ($type->requires_schedule) <x-status-badge size="xs" tone="info">{{ __('Termin') }}</x-status-badge> @endif
                            @if ($type->requires_address) <x-status-badge size="xs" tone="info">{{ __('Adresse') }}</x-status-badge> @endif
                            @if ($type->requires_tour) <x-status-badge size="xs" tone="info">{{ __('Tour') }}</x-status-badge> @endif
                            @if ($type->allow_priority) <x-status-badge size="xs" tone="ghost">{{ __('Priorität') }}</x-status-badge> @endif
                            @if ($type->allow_tour && ! $type->requires_tour) <x-status-badge size="xs" tone="ghost">{{ __('Tour opt.') }}</x-status-badge> @endif
                        </div>
                    </td>
                    <td class="text-center">{{ $type->diary_entries_count ?? 0 }}</td>
                    <td class="text-center">
                        @if ($type->is_active)
                            <x-status-badge tone="success">{{ __('Ja') }}</x-status-badge>
                        @else
                            <x-status-badge tone="error">{{ __('Nein') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="edit"
                                        data-entry-modal-trigger
                                        :href="route('admin.entry-types.edit', $type)"
                                        :label="__('Bearbeiten')" />
                            <form method="POST" action="{{ route('admin.entry-types.destroy', $type) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-message="{{ __('Eintragstyp wirklich löschen?') }}"
                                  data-confirm-label="{{ __('Löschen') }}">
                                @csrf @method('DELETE')
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">list_alt</span>' :colspan="7" :title="__('Keine Eintragstypen vorhanden')" compact />
            @endforelse
    </x-table>

    <x-pagination :paginator="$entryTypes" />
</x-index-page>
@endsection
