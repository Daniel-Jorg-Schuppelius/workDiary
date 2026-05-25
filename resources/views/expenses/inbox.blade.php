@extends('layouts.app')

@section('title', __('Spesen-Genehmigung'))
@section('nav-title', __('Spesen-Genehmigung'))

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('Eingereichte Spesen prüfen, genehmigen oder ablehnen.')" />
        </x-slot:toolbar>

        <x-filter-bar :action="route('expense-approvals.inbox')" :reset="route('expense-approvals.inbox')">
            <x-filter-field :label="__('Status')" for="inbox-status">
                <select id="inbox-status" name="status" class="select select-sm select-bordered shrink-0"
                        onchange="this.form.submit()">
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

        <x-card padding="p-0">
            @if ($bulkEnabled)
                <form method="POST" action="{{ route('expense-approvals.bulk-approve') }}" data-bulk-form>
                    @csrf
                    <div class="px-3 pt-3">
                        <x-bulk-toolbar :label="__(':n Spesen ausgewählt')">
                            <x-slot:actions>
                                <button type="submit"
                                        formaction="{{ route('expense-approvals.bulk-approve') }}"
                                        class="btn btn-success btn-sm"
                                        onclick="return confirm('{{ __('Alle ausgewählten Spesen genehmigen?') }}');">
                                    <x-icon name="check_circle" /> {{ __('Genehmigen') }}
                                </button>
                                <button type="submit"
                                        formaction="{{ route('expense-approvals.bulk-reject') }}"
                                        class="btn btn-error btn-sm"
                                        onclick="return confirm('{{ __('Alle ausgewählten Spesen ablehnen?') }}');">
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
                     bare>
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
                                       data-bulk-checkbox name="ids[]" value="{{ $expense->id }}"
                                       aria-label="{{ __('Spese :id auswählen', ['id' => $expense->id]) }}">
                            </td>
                        @endif
                        <td class="whitespace-nowrap">{{ $expense->date->format('d.m.Y') }}</td>
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
                            {{ number_format((float) $expense->amount_gross, 2, ',', '.') }} {{ $expense->currency }}
                            @if ($expense->billable)
                                <span class="badge badge-ghost badge-xs ml-1">{{ __('weiterberechnet') }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-{{ $expense->status->tone() }} badge-sm">
                                {{ $expense->status->label() }}
                            </span>
                            @if ($expense->status === \App\Enums\Expense\ExpenseStatus::Rejected && $expense->reject_reason)
                                <div class="text-xs text-error mt-1">{{ $expense->reject_reason }}</div>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @if ($expense->status === \App\Enums\Expense\ExpenseStatus::Pending)
                                <form method="POST" action="{{ route('expense-approvals.approve', $expense) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Spese genehmigen?') }}"
                                      data-confirm-label="{{ __('Genehmigen') }}">
                                    @csrf
                                    <x-icon-btn icon="check_circle" tone="success" size="sm" type="submit"
                                                :label="__('Genehmigen')" />
                                </form>
                                <x-icon-btn icon="block" tone="error" size="sm"
                                            data-entry-modal-trigger
                                            :href="route('expense-approvals.reject-form', $expense)"
                                            :label="__('Ablehnen')" />
                            @elseif ($expense->status === \App\Enums\Expense\ExpenseStatus::Approved)
                                <form method="POST" action="{{ route('expense-approvals.reimburse', $expense) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Als erstattet markieren?') }}"
                                      data-confirm-label="{{ __('Markieren') }}">
                                    @csrf
                                    <x-icon-btn icon="payments" tone="info" size="sm" type="submit"
                                                :label="__('Als erstattet markieren')" />
                                </form>
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

        @if ($expenses->hasPages())
            {{ $expenses->links() }}
        @endif
    </x-page-shell>
@endsection
