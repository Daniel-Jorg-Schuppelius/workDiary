{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : closing.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Periodenabschluss (Feature 125, MVP-677). Vorläufig geschlossen ist ein
  Signal, endgültig geschlossen eine Sperre — die Wiedereröffnung braucht ein
  eigenes Recht und eine Begründung.
--}}

@extends('layouts.app')

@section('title', __('accounting.closing.title'))
@section('nav-title', __('accounting.closing.title'))

@section('content')
    <x-index-page :subtitle="__('accounting.closing.subtitle')">
        <x-accounting.sovereignty-note />

        @forelse ($years as $year)
            <x-card :title="$year->label" icon="calendar_month"
                    :subtitle="$year->starts_on->fdate() . ' – ' . $year->ends_on->fdate()">
                <x-slot:actions>
                    <x-status-badge :tone="$year->status->tone()">{{ $year->status->label() }}</x-status-badge>
                    @if ($canPrepare && ! $year->status->isHardClosed())
                        {{-- Jahres-AfA als Inbox-Entwürfe (Feature 133) — gebucht wird in der Inbox. --}}
                        <x-action-form :action="route('finance.accounting.closing.depreciation', $year)" method="POST"
                                       :confirm="__('accounting.closing.confirm.depreciation', ['year' => $year->label])">
                            <x-button type="submit" tone="ghost" size="sm">{{ __('accounting.closing.action.propose_depreciation') }}</x-button>
                        </x-action-form>
                    @endif
                    @if ($canClose && ! $year->status->isHardClosed())
                        <x-action-form :action="route('finance.accounting.closing.close-year', $year)" method="POST"
                                       :confirm="__('accounting.closing.confirm.year')">
                            <x-button type="submit" tone="ghost" size="sm">{{ __('accounting.closing.action.close_year') }}</x-button>
                        </x-action-form>
                    @endif
                </x-slot:actions>

                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('accounting.closing.column.period') }}</th>
                            <th>{{ __('accounting.ledger.column.status') }}</th>
                            <th>{{ __('accounting.closing.column.closed_at') }}</th>
                            <th>{{ __('accounting.closing.column.reopened') }}</th>
                            <th class="text-right"></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($year->periods as $period)
                        <tr class="hover">
                            <td class="font-medium">{{ $period->starts_on->fdate() }} – {{ $period->ends_on->fdate() }}</td>
                            <td><x-status-badge :tone="$period->status->tone()">{{ $period->status->label() }}</x-status-badge></td>
                            <td>{{ $period->closed_at?->fdatetime() ?? '—' }}</td>
                            <td class="text-xs text-base-content/70">
                                @if ($period->reopened_at)
                                    {{ $period->reopened_at->fdate() }} · {{ $period->reopen_reason }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1">
                                    @if ($canClose && $period->status->acceptsPostings())
                                        <x-action-form :action="route('finance.accounting.closing.soft-close', $period)" method="POST">
                                            <x-icon-btn icon="pause_circle" size="xs" tone="ghost" type="submit"
                                                        :label="__('accounting.closing.action.soft_close')" />
                                        </x-action-form>
                                    @endif
                                    @if ($canClose && ! $period->status->isHardClosed())
                                        <x-icon-btn icon="lock" size="xs" tone="ghost"
                                                    data-entry-modal-trigger
                                                    :href="route('finance.accounting.closing.preflight', $period)"
                                                    :label="__('accounting.closing.action.close')" />
                                    @endif
                                    @if ($canReopen && ! $period->status->acceptsPostings())
                                        <x-icon-btn icon="lock_open" size="xs" tone="warning"
                                                    data-entry-modal-trigger
                                                    :href="route('finance.accounting.closing.reopen-form', $period)"
                                                    :label="__('accounting.closing.action.reopen')" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @empty
            <x-empty-state icon="calendar_month" :title="__('accounting.ledger.empty.fiscal_years')" />
        @endforelse

        @if ($canClose)
            <x-card :title="__('accounting.opening.title')" icon="upload_file" :subtitle="__('accounting.opening.subtitle')">
                <form method="POST" action="{{ route('finance.accounting.closing.opening-balances') }}"
                      enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                    @csrf
                    <div class="fieldset">
                        <label class="fieldset-label" for="opening-file">{{ __('accounting.opening.field.file') }}</label>
                        <input id="opening-file" type="file" name="file" accept=".csv,text/csv"
                               class="file-input file-input-bordered file-input-sm w-full" required>
                    </div>
                    <button type="submit" name="dry_run" value="1" class="btn btn-ghost btn-sm">{{ __('accounting.opening.action.dry_run') }}</button>
                    <button type="submit" name="dry_run" value="0" class="btn btn-primary btn-sm">{{ __('accounting.opening.action.import') }}</button>
                </form>
                <p class="mt-2 text-xs text-muted">{{ __('accounting.opening.hint') }}</p>
            </x-card>

            <x-card :title="__('accounting.datev.title')" icon="account_tree" :subtitle="__('accounting.datev.subtitle')">
                <form method="POST" action="{{ route('finance.accounting.closing.datev') }}" class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                    @csrf
                    <x-date-range layout="split" from-name="from" to-name="to"
                                  :from-label="__('accounting.ledger.column.from')"
                                  :to-label="__('accounting.ledger.column.to')"
                                  :from="now()->startOfYear()->toDateString()"
                                  :to="now()->toDateString()" />
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('accounting.datev.action.export') }}</button>
                </form>
                <p class="mt-2 text-xs text-muted">{{ __('accounting.datev.hint') }}</p>
            </x-card>
        @endif
    </x-index-page>
@endsection
