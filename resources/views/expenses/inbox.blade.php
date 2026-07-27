@extends('layouts.app')

@section('title', __('Spesen-Genehmigung'))
@section('nav-title', __('Spesen-Genehmigung'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Eingereichte Spesen prüfen, genehmigen oder ablehnen.')">

        <x-filter-bar :action="route('expense-approvals.inbox')" :reset="route('expense-approvals.inbox')">
            <x-filter-field :label="__('Status')" for="inbox-status">
                <select id="inbox-status" name="status" class="select select-sm select-bordered shrink-0"
                        data-autosubmit>
                    @foreach ($statusOptions as $opt)
                        <option value="{{ $opt->value }}" @selected($statusEnum === $opt)>{{ $opt->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-kpi-tile :label="__('Offen')"
                        :value="(string) ($counts[\App\Enums\Expense\ExpenseStatus::Pending->value] ?? 0)"
                        :tone="($counts[\App\Enums\Expense\ExpenseStatus::Pending->value] ?? 0) > 0 ? 'warning' : 'ghost'" />
            <x-kpi-tile :label="__('Genehmigt, wartet auf Erstattung')"
                        :value="(string) ($counts[\App\Enums\Expense\ExpenseStatus::Approved->value] ?? 0)"
                        tone="info" />
        </div>

        @php($bulkEnabled = $statusEnum === \App\Enums\Expense\ExpenseStatus::Pending && $expenses->isNotEmpty())

        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            @if ($bulkEnabled)
                <form method="POST" action="{{ route('expense-approvals.bulk-approve') }}" data-bulk-form
                      class="min-h-0 flex-1 flex flex-col overflow-hidden">
                    @csrf
                    <div class="px-3 pt-3 flex-none">
                        <x-bulk-toolbar :label="__(':n Spesen ausgewählt')">
                            <x-slot:actions>
                                <button type="submit"
                                        formaction="{{ route('expense-approvals.bulk-approve') }}"
                                        class="btn btn-success btn-sm"
                                        data-confirm-dialog
                                        data-confirm-message="{{ __('Alle ausgewählten Spesen genehmigen?') }}"
                                        data-confirm-icon="check_circle"
                                        data-confirm-tone="success"
                                        data-confirm-label="{{ __('Genehmigen') }}">
                                    <x-icon name="check_circle" /> {{ __('Genehmigen') }}
                                </button>
                                <button type="submit"
                                        formaction="{{ route('expense-approvals.bulk-reject') }}"
                                        class="btn btn-error btn-sm"
                                        data-confirm-dialog
                                        data-confirm-message="{{ __('Alle ausgewählten Spesen ablehnen?') }}"
                                        data-confirm-icon="block"
                                        data-confirm-tone="error"
                                        data-confirm-label="{{ __('Ablehnen') }}">
                                    <x-icon name="block" /> {{ __('Ablehnen') }}
                                </button>
                            </x-slot:actions>
                        </x-bulk-toolbar>
                    </div>
            @endif
            <x-table table-sort="server"
                     :route="route('expense-approvals.inbox')"
                     :current-sort="$sort ?? null"
                     :current-dir="$dir ?? 'asc'"
                     :sort-params="['status' => $statusEnum->value]"
                     bare scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        @if ($bulkEnabled)
                            <th class="w-8">
                                <input type="checkbox" class="checkbox checkbox-sm" data-bulk-select-all
                                       aria-label="{{ __('Alle auswählen') }}">
                            </th>
                        @endif
                        <x-table.th sort="date" default>{{ __('Datum') }}</x-table.th>
                        <x-table.th sort="owner">{{ __('Mitarbeiter') }}</x-table.th>
                        <x-table.th>{{ __('Kategorie') }}</x-table.th>
                        <x-table.th>{{ __('Beschreibung') }}</x-table.th>
                        <x-table.th sort="amount" align="right">{{ __('Brutto') }}</x-table.th>
                        <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($expenses as $expense)
                    <tr>
                        @if ($bulkEnabled)
                            <td>
                                <input type="checkbox" class="checkbox checkbox-sm"
                                       data-bulk-checkbox name="ids[]" value="{{ $expense->sqid }}"
                                       aria-label="{{ __('Spese :id auswählen', ['id' => $expense->id]) }}">
                            </td>
                        @endif
                        <td class="whitespace-nowrap">{{ $expense->date->fdate() }}</td>
                        <td>
                            <div class="font-medium">{{ $expense->user?->name ?? '—' }}</div>
                            @if ($expense->project)
                                <div class="text-xs text-base-content/60">{{ $expense->project->name }}</div>
                            @endif
                        </td>
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
                        <td class="max-w-xs truncate">
                            {{ $expense->description }}
                            @if ($expense->vendor)
                                <div class="text-xs text-base-content/60">{{ $expense->vendor }}</div>
                            @endif
                        </td>
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
                            @if ($expense->status === \App\Enums\Expense\ExpenseStatus::Rejected && $expense->reject_reason)
                                <div class="text-xs text-error mt-1">{{ $expense->reject_reason }}</div>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @if ($expense->status === \App\Enums\Expense\ExpenseStatus::Pending)
                                <x-action-form :action="route('expense-approvals.approve', $expense)"
                                      :confirm="__('Spese genehmigen?')"
                                      :confirm-label="__('Genehmigen')">
                                    <x-icon-btn icon="check_circle" tone="success" size="sm" type="submit"
                                                :label="__('Genehmigen')" />
                                </x-action-form>
                                <x-icon-btn icon="block" tone="error" size="sm"
                                            data-entry-modal-trigger
                                            :href="route('expense-approvals.reject-form', $expense)"
                                            :label="__('Ablehnen')" />
                            @elseif ($expense->status === \App\Enums\Expense\ExpenseStatus::Approved)
                                <x-action-form :action="route('expense-approvals.reimburse', $expense)"
                                      :confirm="__('Als erstattet markieren?')"
                                      :confirm-label="__('Markieren')">
                                    <x-icon-btn icon="payments" tone="info" size="sm" type="submit"
                                                :label="__('Als erstattet markieren')" />
                                </x-action-form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">inbox</span>'
                                   :colspan="$bulkEnabled ? 8 : 7"
                                   :title="__('Keine Spesen in diesem Status')"
                                   compact />
                @endforelse
            </x-table>
            @if ($bulkEnabled)
                </form>
            @endif
        </x-card>

        <x-pagination :paginator="$expenses" standing />
    </x-index-page>
@endsection
