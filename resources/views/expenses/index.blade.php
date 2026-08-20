{{--
  Created on   : Fri May 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Spesen & Auslagen'))
@section('nav-title', __('Spesen & Auslagen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Belege & Auslagen erfassen, prüfen und freigeben.')">
        <x-slot:actions>
            <x-icon-btn icon="download" tone="ghost" size="sm"
                        :href="route('expenses.export', ['status' => $statusFilter])"
                        show-label>{{ __('CSV-Export') }}</x-icon-btn>
            {{-- Feature 088 P3: Beleg-Scan (PDF oder Foto) → Entwurfs-Auslage mit
                 Vorschlagswerten; der Mensch prüft im Formular. --}}
            <form method="POST" action="{{ route('expenses.scan') }}" enctype="multipart/form-data"
                  class="inline-flex items-center gap-1">
                @csrf
                <input type="file" name="receipt" required accept="application/pdf,image/jpeg,image/png,image/tiff"
                       class="file-input file-input-bordered file-input-sm w-52"
                       aria-label="{{ __('Beleg scannen (PDF oder Foto)') }}">
                <button type="submit" class="btn btn-ghost btn-sm gap-1">
                    <span class="material-symbols-outlined text-base" aria-hidden="true">document_scanner</span>
                    {{ __('Scannen') }}
                </button>
            </form>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('expenses.create')"
                        show-label>{{ __('Neue Spese') }}</x-icon-btn>
        </x-slot:actions>

        <x-filter-bar :action="route('expenses.index')" :reset="route('expenses.index')">
            <x-filter-field :label="__('Status')" for="exp-status">
                <select id="exp-status" name="status" class="select select-sm select-bordered shrink-0"
                        data-autosubmit>
                    <option value="">{{ __('Alle Status') }}</option>
                    @foreach ($statusOptions as $opt)
                        <option value="{{ $opt->value }}" @selected($statusFilter === $opt->value)>{{ $opt->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <x-kpi-tile :label="__('Summe (Brutto)')"
                        :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['gross'], 2, withThousandsSeparator: true) . ' €'" />
            <x-kpi-tile :label="__('Diesen Monat')"
                        :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['current_month'], 2, withThousandsSeparator: true) . ' €'" />
            <x-kpi-tile :label="__('Privat verauslagt (erstattbar)')"
                        :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['reimbursable'], 2, withThousandsSeparator: true) . ' €'"
                        tone="info" />
            <x-kpi-tile :label="__('Erstattung ausstehend')"
                        :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['reimbursement_pending'], 2, withThousandsSeparator: true) . ' €'"
                        :tone="$totals['reimbursement_pending'] > 0 ? 'warning' : 'ghost'" />
            <x-kpi-tile :label="__('Offene Genehmigungen')"
                        :value="(string) $totals['pending']"
                        :tone="$totals['pending'] > 0 ? 'warning' : 'ghost'" />
        </div>

        <x-table :zebra="true" table-sort="server"
                 :route="route('expenses.index')"
                 :current-sort="$sort ?? null"
                 :current-dir="$dir ?? 'desc'"
                 :sort-params="['status' => $statusFilter]"
                 scroll="flex" :pinRows="true">
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
                    <td class="whitespace-nowrap">{{ $expense->date?->fdate() }}</td>
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
                        {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($expense->amount_gross?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} {{ $expense->currency->value }}
                        @if ($expense->billable)
                            <x-status-badge tone="ghost" size="xs" class="ml-1">{{ __('weiterberechnet') }}</x-status-badge>
                        @endif
                    </td>
                    <td>
                        <x-status-badge :tone="$expense->status->tone()" size="sm">
                            {{ $expense->status->label() }}
                        </x-status-badge>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        {{-- MVP-550: Belegdatei — Zähler zeigt, ob die Auslage belegt ist. --}}
                        <x-icon-btn icon="attach_file"
                                    :tone="$expense->attachments_count > 0 ? 'success' : null"
                                    data-entry-modal-trigger
                                    :href="route('expenses.receipt', $expense)"
                                    :label="__('expenses.receipt.title')" />
                        @can('submit', $expense)
                            <x-action-form :action="route('expenses.submit', $expense)">
                                <x-icon-btn icon="send" tone="warning" size="sm" type="submit"
                                            :label="__('Zur Genehmigung einreichen')" />
                            </x-action-form>
                        @endcan
                        @can('update', $expense)
                            <x-icon-btn icon="edit"
                                        data-entry-modal-trigger
                                        :href="route('expenses.edit', $expense)"
                                        :label="__('Bearbeiten')" />
                        @endcan
                        @can('delete', $expense)
                            <x-action-form :action="route('expenses.destroy', $expense)" method="DELETE"
                                  :confirm="__('Spese wirklich löschen?')"
                                  :confirm-label="__('Löschen')">
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </x-action-form>
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

        <x-pagination :paginator="$expenses" standing />
    </x-index-page>
@endsection
