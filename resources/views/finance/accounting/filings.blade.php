{{--
  Created on   : Sun Aug 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : filings.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Steuertermine (Feature 125, MVP-686). Die Fristen sind berechnet — aus
  Meldeprofil, Periode und Feiertagskalender (§ 108 Abs. 3 AO). Gespeichert
  wird nur, was erledigt ist.
--}}

@extends('layouts.app')

@section('title', __('accounting.filing.calendar.title'))
@section('nav-title', __('accounting.filing.calendar.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('accounting.filing.calendar.subtitle')">
        <x-accounting.sovereignty-note />

        <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
            <x-icon name="info" />
            <div>
                <span>{{ __('accounting.filing.calendar.hint') }}</span>
                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                    <x-status-badge :tone="$interval->tone()">{{ $interval->label() }}</x-status-badge>
                    @if ($hasExtension)
                        <x-status-badge tone="success">{{ __('accounting.filing.extension.short') }}</x-status-badge>
                    @endif
                    @if ($taxAdvised)
                        <x-status-badge tone="neutral">{{ __('accounting.filing.calendar.tax_advised') }}</x-status-badge>
                    @endif
                </div>
            </div>
        </div>

        @if ($noReturns)
            <div class="alert bg-warning/10 border-warning/30 text-sm text-base-content" role="note">
                <x-icon name="info" />
                <span>{{ __('accounting.filing.no_period') }}</span>
            </div>
        @endif

        @if ($overdue->isNotEmpty())
            <x-card :title="__('accounting.filing.calendar.overdue')" icon="warning">
                <ul class="list-disc pl-5 text-sm">
                    @foreach ($overdue as $item)
                        <li>
                            {{ $item->kind->label() }} · {{ $item->period_key }} ·
                            <span class="text-error">{{ $item->due_on->fdate() }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <x-filter-bar :action="route('finance.accounting.filings.index')" :reset="route('finance.accounting.filings.index')">
            <select name="year" class="select select-sm select-bordered w-36 shrink-0" aria-label="{{ __('accounting.filing.field.year') }}">
                @foreach ($years as $option)
                    <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
                @endforeach
            </select>
        </x-filter-bar>

        <x-table scroll="flex" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.filing.calendar.column.kind') }}</th>
                    <th>{{ __('accounting.filing.field.period') }}</th>
                    <th>{{ __('accounting.filing.calendar.column.due_on') }}</th>
                    <th>{{ __('accounting.ledger.column.status') }}</th>
                    <th>{{ __('accounting.ledger.field.note') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($obligations as $obligation)
                <tr class="hover">
                    <td><x-status-badge :tone="$obligation->kind->tone()">{{ $obligation->kind->label() }}</x-status-badge></td>
                    <td class="font-mono text-sm">{{ $obligation->period_key }}</td>
                    <td @class(['whitespace-nowrap', 'text-error font-medium' => ! $obligation->status->isDone() && $obligation->due_on->isPast()])>
                        {{ $obligation->due_on->fdate() }}
                    </td>
                    <td><x-status-badge :tone="$obligation->status->tone()">{{ $obligation->status->label() }}</x-status-badge></td>
                    <td class="max-w-xs truncate text-xs text-base-content/70">{{ $obligation->note }}</td>
                    <td class="text-right">
                        @if ($canManage && ! $obligation->status->isDone())
                            <div class="flex justify-end gap-1">
                                <form method="POST" action="{{ route('finance.accounting.filings.mark', $obligation) }}" class="flex items-center gap-1">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ \App\Enums\Finance\FilingObligationStatus::Submitted->value }}">
                                    <input aria-label="{{ __('accounting.ledger.field.note') }}" type="text" name="note" maxlength="500" class="input input-xs input-bordered w-40"
                                           placeholder="{{ __('accounting.ledger.field.note') }}">
                                    <x-icon-btn icon="task_alt" size="xs" tone="primary" type="submit"
                                                :label="__('accounting.filing.calendar.action.submitted')" />
                                </form>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="6" icon="event_available" :title="__('accounting.filing.calendar.empty')" compact />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
