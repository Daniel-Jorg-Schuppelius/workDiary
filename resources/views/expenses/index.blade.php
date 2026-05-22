@extends('layouts.app')

@section('title', __('Spesen & Auslagen'))
@section('nav-title', __('Spesen & Auslagen'))

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar>
                <x-slot:actions>
                    <form method="GET" action="{{ route('expenses.index') }}" class="flex items-center gap-1">
                        <label for="exp-status" class="sr-only">{{ __('Status') }}</label>
                        <select id="exp-status" name="status"
                                class="select select-bordered select-sm"
                                onchange="this.form.submit()">
                            <option value="">{{ __('Alle Status') }}</option>
                            @foreach ($statusOptions as $opt)
                                <option value="{{ $opt->value }}" @selected($statusFilter === $opt->value)>{{ $opt->label() }}</option>
                            @endforeach
                        </select>
                        @if ($statusFilter !== '')
                            <x-icon-btn icon="restart_alt" tone="ghost" size="sm"
                                        :href="route('expenses.index')"
                                        :label="__('Filter zurücksetzen')" />
                        @endif
                    </form>
                    <x-icon-btn icon="download" tone="ghost" size="sm"
                                :href="route('expenses.export', ['status' => $statusFilter])"
                                show-label>{{ __('CSV-Export') }}</x-icon-btn>
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('expenses.create')"
                                show-label>{{ __('Neue Spese') }}</x-icon-btn>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <x-kpi-tile :label="__('Summe (Brutto)')"
                        :value="number_format($totals['gross'], 2, ',', '.') . ' €'" />
            <x-kpi-tile :label="__('Diesen Monat')"
                        :value="number_format($totals['current_month'], 2, ',', '.') . ' €'" />
            <x-kpi-tile :label="__('Privat verauslagt (erstattbar)')"
                        :value="number_format($totals['reimbursable'], 2, ',', '.') . ' €'"
                        tone="info" />
            <x-kpi-tile :label="__('Erstattung ausstehend')"
                        :value="number_format($totals['reimbursement_pending'], 2, ',', '.') . ' €'"
                        :tone="$totals['reimbursement_pending'] > 0 ? 'warning' : 'ghost'" />
            <x-kpi-tile :label="__('Offene Genehmigungen')"
                        :value="(string) $totals['pending']"
                        :tone="$totals['pending'] > 0 ? 'warning' : 'ghost'" />
        </div>

        <x-card padding="p-0">
            <x-table table-sort="server"
                     :route="route('expenses.index')"
                     :current-sort="$sort ?? null"
                     :current-dir="$dir ?? 'desc'"
                     :sort-params="['status' => $statusFilter]"
                     bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort="date" default>{{ __('Datum') }}</x-table.th>
                        <x-table.th>{{ __('Kategorie') }}</x-table.th>
                        <x-table.th sort="vendor">{{ __('Beleg/Anbieter') }}</x-table.th>
                        <x-table.th sort="description">{{ __('Beschreibung') }}</x-table.th>
                        <x-table.th sort="amount" align="right">{{ __('Brutto') }}</x-table.th>
                        <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="whitespace-nowrap">{{ $expense->date?->format('d.m.Y') }}</td>
                        <td>
                            @if ($expense->category)
                                <span class="inline-flex items-center gap-1">
                                    <x-icon :name="$expense->category->icon ?: 'receipt_long'"
                                            class="text-{{ $expense->category->color ?: 'primary' }}" />
                                    {{ $expense->category->label }}
                                </span>
                            @else
                                <span class="text-base-content/50">—</span>
                            @endif
                        </td>
                        <td>{{ $expense->vendor ?: '—' }}</td>
                        <td class="max-w-xs truncate">{{ $expense->description }}</td>
                        <td class="text-right whitespace-nowrap">
                            {{ number_format((float) $expense->amount_gross, 2, ',', '.') }} {{ $expense->currency }}
                            @if ($expense->billable)
                                <span class="badge badge-ghost badge-xs ml-1">{{ __('weiterberechnet') }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-{{ $expense->status->tone() }} badge-sm">
                                {{ $expense->status->label() }}
                            </span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @can('submit', $expense)
                                <form method="POST" action="{{ route('expenses.submit', $expense) }}" class="inline">
                                    @csrf
                                    <x-icon-btn icon="send" tone="warning" size="sm" type="submit"
                                                :label="__('Zur Genehmigung einreichen')" />
                                </form>
                            @endcan
                            @can('update', $expense)
                                <x-icon-btn icon="edit"
                                            data-entry-modal-trigger
                                            :href="route('expenses.edit', $expense)"
                                            :label="__('Bearbeiten')" />
                            @endcan
                            @can('delete', $expense)
                                <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Spese wirklich löschen?') }}"
                                      data-confirm-label="{{ __('Löschen') }}">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>'
                                   :colspan="7"
                                   :title="__('Keine Spesen im gewählten Zeitraum')"
                                   compact />
                @endforelse
            </x-table>
        </x-card>

        @if ($expenses->hasPages())
            {{ $expenses->links() }}
        @endif
    </x-page-shell>
@endsection
