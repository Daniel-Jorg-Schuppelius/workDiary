{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : entry.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Einzelne Buchung (Feature 125, MVP-672): Kopf, Zeilen und — bei Storno —
  der Bezug in beide Richtungen. Eine Festbuchung wird hier nur gelesen.
--}}

@extends('layouts.app')

@section('title', __('accounting.ledger.entry.title'))
@section('nav-title', __('accounting.ledger.entry.title'))

@section('content')
    <x-page-toolbar :subtitle="$entry->memo">
        <x-slot:badges>
            <x-status-badge :tone="$entry->status->tone()">{{ $entry->status->label() }}</x-status-badge>
        </x-slot:badges>
        <x-slot:actions>
            @if ($canPost && $entry->status->isMutable())
                <x-action-form :action="route('finance.accounting.journal.post', $entry)" method="POST">
                    <x-button type="submit" tone="primary" size="sm">{{ __('accounting.ledger.action.post') }}</x-button>
                </x-action-form>
            @endif
            @if ($canPost && $entry->status === \App\Enums\Finance\AccountingEntryStatus::Posted)
                <x-icon-btn icon="undo" size="sm" tone="warning" show-label
                            data-entry-modal-trigger
                            :href="route('finance.accounting.journal.reverse-form', $entry)"
                            :label="__('accounting.ledger.action.reverse')" />
            @endif
        </x-slot:actions>
    </x-page-toolbar>

    <div class="mt-4 grid gap-4">
        <x-card :title="__('accounting.ledger.entry.head')" icon="receipt_long">
            <x-detail-grid layout="cells" :cols="3" small-labels>
                <x-detail-grid.row :label="__('accounting.ledger.column.journal_no')" class="font-mono">{{ $entry->journal_no ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('accounting.ledger.column.booked_on')">{{ $entry->booked_on->fdate() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('accounting.ledger.column.document_on')">{{ $entry->document_on?->fdate() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('accounting.ledger.column.document_reference')">{{ $entry->document_reference ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('accounting.ledger.column.posted_by')">{{ $entry->postedBy?->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('accounting.ledger.column.source')" class="font-mono text-xs">{{ $entry->source_key ?? '—' }}</x-detail-grid.row>
            </x-detail-grid>

            @if ($entry->reverses)
                <div class="alert bg-warning/10 border-warning/30 mt-3 text-sm" role="note">
                    <x-icon name="undo" />
                    <span>{{ __('accounting.ledger.entry.is_reversal_of', ['no' => (string) $entry->reverses->journal_no]) }}</span>
                </div>
            @endif
            @if ($entry->reversedBy)
                <div class="alert bg-warning/10 border-warning/30 mt-3 text-sm" role="note">
                    <x-icon name="undo" />
                    <span>{{ __('accounting.ledger.entry.reversed_by', ['no' => (string) $entry->reversedBy->journal_no, 'reason' => (string) $entry->reversal_reason]) }}</span>
                </div>
            @endif
            @foreach ($budgetOverruns ?? [] as $overrun)
                <div class="alert bg-warning/10 border-warning/30 mt-3 text-sm" role="note">
                    <x-icon name="savings" />
                    <span>{{ __('accounting.budget.overrun', [
                        'account' => $overrun['account']->number . ' ' . $overrun['account']->name,
                        'center' => $overrun['cost_center'] !== null ? $overrun['cost_center']->code : '—',
                        'month' => $overrun['month']->translatedFormat('M Y'),
                        'actual' => $overrun['actual']->format(),
                        'budget' => $overrun['budget']->format(),
                    ]) }}</span>
                </div>
            @endforeach
        </x-card>

        <x-card :title="__('accounting.ledger.entry.lines')" icon="table_rows">
            <x-table :bare="true">
                <x-slot:head>
                    <tr>
                        <th>#</th>
                        <th>{{ __('accounting.ledger.column.account') }}</th>
                        <th>{{ __('accounting.ledger.column.memo') }}</th>
                        <th class="text-right">{{ __('accounting.ledger.column.debit') }}</th>
                        <th class="text-right">{{ __('accounting.ledger.column.credit') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($entry->lines as $line)
                    <tr class="hover">
                        <td class="font-mono">{{ $line->line_no }}</td>
                        <td>{{ $line->account?->displayLabel() ?? '—' }}</td>
                        <td class="text-sm text-base-content/70">{{ $line->memo }}</td>
                        <td class="text-right font-mono">{{ $line->debit?->format() ?? '' }}</td>
                        <td class="text-right font-mono">{{ $line->credit?->format() ?? '' }}</td>
                    </tr>
                @endforeach
                <tr class="font-semibold">
                    <td colspan="3">{{ __('accounting.ledger.entry.total') }}</td>
                    <td class="text-right font-mono">{{ $entry->debitTotal()->format() }}</td>
                    <td class="text-right font-mono">{{ $entry->creditTotal()->format() }}</td>
                </tr>
            </x-table>
        </x-card>
    </div>
@endsection
