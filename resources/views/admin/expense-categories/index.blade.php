@extends('layouts.app')

@section('title', __('Spesenkategorien'))
@section('nav-title', __('Spesenkategorien'))

@section('content')
<x-page-shell gap="6">
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Spesen-Kategorien für Belegerfassung verwalten.')">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('admin.expense-categories.create')"
                            show-label>{{ __('Kategorie anlegen') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div role="alert" class="alert alert-info alert-soft">
        <x-icon name="info" />
        <div>
            <h3 class="font-semibold">{{ __('Was sind Spesenkategorien?') }}</h3>
            <div class="text-sm">
                {{ __('Spesenkategorien strukturieren die von Mitarbeitenden erfassten Auslagen (z. B. Verpflegung, Übernachtung, Bewirtung). Sie legen Standardwerte für Steuersatz, Belegpflicht und ob Kosten standardmäßig an Kund:innen weiterberechnet werden fest und steuern Symbol und Farbe in Listen und Auswertungen.') }}
            </div>
        </div>
    </div>

    <x-table table-sort="server"
             :route="route('admin.expense-categories.index')"
             :current-sort="$sort ?? null"
             :current-dir="$dir ?? 'asc'">
        <x-slot:head>
            <tr>
                <x-table.th sort="sort" default class="w-12">#</x-table.th>
                <x-table.th sort="label">{{ __('Bezeichnung') }}</x-table.th>
                <x-table.th sort="slug">{{ __('Slug') }}</x-table.th>
                <x-table.th sort="tax" align="right">{{ __('MwSt.') }}</x-table.th>
                <th>{{ __('Flags') }}</th>
                <x-table.th sort="expenses" align="center">{{ __('Belege') }}</x-table.th>
                <x-table.th sort="is_active" align="center">{{ __('Aktiv') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($categories as $cat)
            <tr>
                <td class="text-base-content/60">{{ $cat->sort }}</td>
                <td class="font-medium">
                    <span class="inline-flex items-center gap-2">
                        <x-icon :name="$cat->icon ?: 'receipt_long'" class="text-{{ $cat->color ?: 'primary' }}" />
                        {{ $cat->label }}
                    </span>
                    @if ($cat->description)
                        <div class="text-xs text-base-content/60">{{ $cat->description }}</div>
                    @endif
                </td>
                <td class="font-mono text-sm text-base-content/60">{{ $cat->slug }}</td>
                <td class="text-right tabular-nums">{{ number_format((float) $cat->default_tax_rate, 2, ',', '.') }} %</td>
                <td>
                    <div class="flex flex-wrap gap-1">
                        @if ($cat->default_billable) <span class="badge badge-xs badge-info">{{ __('Berechenbar') }}</span> @endif
                        @if ($cat->requires_receipt) <span class="badge badge-xs badge-warning">{{ __('Belegpflicht') }}</span> @endif
                    </div>
                </td>
                <td class="text-center">{{ $cat->expenses_count ?? 0 }}</td>
                <td class="text-center">
                    @if ($cat->is_active)
                        <span class="badge badge-success badge-sm">{{ __('Ja') }}</span>
                    @else
                        <span class="badge badge-error badge-sm">{{ __('Nein') }}</span>
                    @endif
                </td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="edit"
                                    data-entry-modal-trigger
                                    :href="route('admin.expense-categories.edit', $cat)"
                                    :label="__('Bearbeiten')" />
                        <form method="POST" action="{{ route('admin.expense-categories.destroy', $cat) }}" class="inline"
                              data-confirm-dialog
                              data-confirm-message="{{ __('Kategorie wirklich löschen?') }}"
                              data-confirm-label="{{ __('Löschen') }}">
                            @csrf @method('DELETE')
                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>' :colspan="8" :title="__('Keine Spesenkategorien vorhanden')" compact />
        @endforelse
    </x-table>

    @if ($categories->hasPages())
        <div>{{ $categories->links() }}</div>
    @endif
</x-page-shell>
@endsection
