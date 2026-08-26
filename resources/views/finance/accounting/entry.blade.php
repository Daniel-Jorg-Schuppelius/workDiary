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
        <x-slot:actions>
            <x-status-badge :tone="$entry->status->tone()">{{ $entry->status->label() }}</x-status-badge>
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
            <dl class="grid gap-3 sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-muted">{{ __('accounting.ledger.column.journal_no') }}</dt>
                    <dd class="font-mono">{{ $entry->journal_no ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">{{ __('accounting.ledger.column.booked_on') }}</dt>
                    <dd>{{ $entry->booked_on->fdate() }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">{{ __('accounting.ledger.column.document_on') }}</dt>
                    <dd>{{ $entry->document_on?->fdate() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">{{ __('accounting.ledger.column.document_reference') }}</dt>
                    <dd>{{ $entry->document_reference ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">{{ __('accounting.ledger.column.posted_by') }}</dt>
                    <dd>{{ $entry->postedBy?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">{{ __('accounting.ledger.column.source') }}</dt>
                    <dd class="font-mono text-xs">{{ $entry->source_key ?? '—' }}</dd>
                </div>
            </dl>

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
