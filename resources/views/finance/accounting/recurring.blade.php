{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : recurring.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Wiederkehrende Vorgänge (Feature 125, MVP-675). Drei Arten nebeneinander:
  Serienrechnungen bleiben beim Abrechnungsplan und werden hier nur verlinkt;
  Belegerwartungen warten auf ihr Original; Buchungsvorlagen erzeugen Entwürfe.
--}}

@extends('layouts.app')

@section('title', __('accounting.recurring.title'))
@section('nav-title', __('accounting.recurring.title'))

@section('content')
    <x-index-page :subtitle="__('accounting.recurring.subtitle')">
        <x-slot:actions>
            @if ($canConfigure)
                <x-icon-btn icon="add" size="sm" tone="primary"
                            data-entry-modal-trigger
                            :href="route('finance.accounting.recurring.create')"
                            :label="__('accounting.recurring.action.add')" />
            @endif
        </x-slot:actions>

        <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
            <x-icon name="info" />
            <span>{{ __('accounting.recurring.principle') }}</span>
        </div>

        <x-card :title="__('accounting.recurring.section.open_runs')" icon="pending_actions">
            <x-table :bare="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('accounting.recurring.column.template') }}</th>
                        <th>{{ __('accounting.recurring.column.period') }}</th>
                        <th>{{ __('accounting.open_items.column.due_date') }}</th>
                        <th class="text-right">{{ __('accounting.recurring.column.expected') }}</th>
                        <th>{{ __('accounting.ledger.column.status') }}</th>
                        <th class="text-right"></th>
                    </tr>
                </x-slot:head>
                @forelse ($openRuns as $run)
                    <tr class="hover">
                        <td class="font-medium">{{ $run->template?->name ?? '—' }}</td>
                        <td class="font-mono">{{ $run->period_key }}</td>
                        <td>{{ $run->due_on->fdate() }}</td>
                        <td class="text-right font-mono">{{ $run->expected_amount?->format() ?? '—' }}</td>
                        <td>
                            <x-status-badge :tone="$run->status->tone()">{{ $run->status->label() }}</x-status-badge>
                            @if ($run->blocked_reason)
                                <div class="mt-1 text-xs text-error">{{ $run->blocked_reason }}</div>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                @if ($run->entry)
                                    <x-icon-btn icon="menu_book" size="xs" tone="ghost"
                                                :href="route('finance.accounting.journal.show', $run->entry)"
                                                :label="__('accounting.open_items.action.show_entry')" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="pending_actions" :title="__('accounting.recurring.empty.runs')" /></td></tr>
                @endforelse
            </x-table>
        </x-card>

        <x-card :title="__('accounting.recurring.section.templates')" icon="event_repeat">
            <x-table :bare="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('accounting.recurring.column.name') }}</th>
                        <th>{{ __('accounting.recurring.column.kind') }}</th>
                        <th>{{ __('accounting.recurring.column.interval') }}</th>
                        <th>{{ __('accounting.recurring.column.next_due') }}</th>
                        <th>{{ __('accounting.recurring.column.responsible') }}</th>
                        <th>{{ __('accounting.ledger.column.status') }}</th>
                        <th class="text-right"></th>
                    </tr>
                </x-slot:head>
                @forelse ($templates as $template)
                    <tr class="hover {{ $template->status->runs() ? '' : 'opacity-60' }}">
                        <td class="font-medium">{{ $template->name }}</td>
                        <td><x-status-badge :tone="$template->kind->tone()">{{ $template->kind->label() }}</x-status-badge></td>
                        <td>{{ $template->interval->label() }}</td>
                        <td>{{ $template->next_due_on?->fdate() ?? '—' }}</td>
                        <td>{{ $template->responsible?->name ?? '—' }}</td>
                        <td>
                            <x-status-badge :tone="$template->status->tone()">{{ $template->status->label() }}</x-status-badge>
                            <span class="ml-1 text-xs opacity-60">v{{ $template->version }}</span>
                        </td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                @if ($canPrepare && $template->status->runs())
                                    <x-action-form :action="route('finance.accounting.recurring.run', $template)" method="POST">
                                        <x-icon-btn icon="play_arrow" size="xs" tone="ghost" type="submit"
                                                    :label="__('accounting.recurring.action.run')" />
                                    </x-action-form>
                                @endif
                                @if ($canConfigure)
                                    <x-icon-btn icon="edit" size="xs" tone="ghost"
                                                data-entry-modal-trigger
                                                :href="route('finance.accounting.recurring.edit', $template)"
                                                :label="__('Bearbeiten')" />
                                    @if ($template->status->runs())
                                        <x-action-form :action="route('finance.accounting.recurring.pause', $template)" method="POST">
                                            <x-icon-btn icon="pause" size="xs" tone="ghost" type="submit"
                                                        :label="__('accounting.recurring.action.pause')" />
                                        </x-action-form>
                                    @else
                                        <x-action-form :action="route('finance.accounting.recurring.resume', $template)" method="POST">
                                            <x-icon-btn icon="play_circle" size="xs" tone="ghost" type="submit"
                                                        :label="__('accounting.recurring.action.resume')" />
                                        </x-action-form>
                                    @endif
                                    <x-action-form :action="route('finance.accounting.recurring.end', $template)" method="POST"
                                                   :confirm="__('accounting.recurring.confirm.end')">
                                        <x-icon-btn icon="stop_circle" size="xs" tone="ghost" type="submit"
                                                    :label="__('accounting.recurring.action.end')" />
                                    </x-action-form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="event_repeat" :title="__('accounting.recurring.empty.templates')" /></td></tr>
                @endforelse
            </x-table>
        </x-card>

        <x-card :title="__('accounting.recurring.section.invoice_schedules')" icon="receipt_long"
                :subtitle="__('accounting.recurring.invoice_schedules_hint')">
            <x-table :bare="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('accounting.recurring.column.name') }}</th>
                        <th>{{ __('accounting.recurring.column.next_due') }}</th>
                        <th class="text-right"></th>
                    </tr>
                </x-slot:head>
                @forelse ($invoiceSchedules as $schedule)
                    <tr class="hover">
                        <td class="font-medium">{{ $schedule->title }}</td>
                        <td>{{ $schedule->next_run_on?->fdate() ?? '—' }}</td>
                        <td class="text-right">
                            <x-icon-btn icon="open_in_new" size="xs" tone="ghost"
                                        :href="route('invoice-schedules.index')"
                                        :label="__('accounting.recurring.action.open_schedules')" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3"><x-empty-state icon="receipt_long" :title="__('accounting.recurring.empty.schedules')" /></td></tr>
                @endforelse
            </x-table>
        </x-card>
    </x-index-page>
@endsection
