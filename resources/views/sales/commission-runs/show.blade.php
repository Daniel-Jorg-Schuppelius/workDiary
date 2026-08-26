{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lauf-Detail (Feature 146): Entwurf zeigt die Vorschau, geschlossener Lauf
  die festgeschriebenen Zeilen. Nach dem Schließen nur noch Rückrechnung.
--}}
@extends('layouts.app')
@section('title', __('commission.page.runs') . ' ' . $run->period)
@section('nav-title', __('commission.page.runs') . ' ' . $run->period)
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$run->period_start->format('d.m.Y') . ' – ' . $run->period_end->format('d.m.Y') . ' · ' . $run->currency->value"
                        :badge="$run->status->label()"
                        :badgeTone="$run->status->tone()">
            <x-slot:actions>
                @if ($canManage && ! $run->isClosed())
                    <x-action-form :action="route('commission-runs.close', $run)"
                                   :confirm="__('commission.confirm.close_run')"
                                   confirm-icon="lock" confirm-tone="warning"
                                   :confirm-label="__('commission.action.close')">
                        <x-icon-btn icon="lock" tone="primary" size="sm" type="submit" show-label>{{ __('commission.action.close') }}</x-icon-btn>
                    </x-action-form>
                @endif
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('commission-runs.export', $run)"
                            show-label>{{ __('commission.action.export') }}</x-icon-btn>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('commission-runs.index')"
                            show-label>{{ __('commission.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-kpi-tile :label="__('commission.field.entry_count')" :value="$totals['count']" tone="neutral" />
        <x-kpi-tile :label="__('commission.field.total_base')" :value="$totals['base']->format()" tone="info" format="raw" />
        <x-kpi-tile :label="__('commission.field.total_commission')" :value="$totals['commission']->format()" tone="primary" format="raw" />
    </div>

    <x-card>
        <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
            <x-icon name="groups" class="text-muted" /> {{ __('commission.section.per_user') }}
        </h3>
        <x-table :bare="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('commission.field.user') }}</th>
                    <th class="text-center">{{ __('commission.field.entry_count') }}</th>
                    <th class="text-right">{{ __('commission.field.total_base') }}</th>
                    <th class="text-right">{{ __('commission.field.total_commission') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($perUser as $entry)
                <tr class="hover">
                    <td class="text-sm font-medium">{{ $entry['user'] }}</td>
                    <td class="text-center text-sm">{{ $entry['count'] }}</td>
                    <td class="text-right font-mono text-sm">{{ $entry['base']->format() }}</td>
                    <td class="text-right font-mono text-sm">{{ $entry['commission']->format() }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="4" :title="__('commission.empty.run_rows')" compact />
            @endforelse
        </x-table>
        @if (! $run->isClosed())
            <p class="mt-3 text-xs text-muted">{{ __('commission.hint.draft_preview') }}</p>
        @endif
    </x-card>

    <x-card>
        <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
            <x-icon name="receipt_long" class="text-muted" /> {{ __('commission.section.run_rows') }}
        </h3>
        <x-table :bare="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('commission.field.earned_on') }}</th>
                    <th>{{ __('commission.field.user') }}</th>
                    <th>{{ __('commission.field.invoice') }}</th>
                    <th>{{ __('commission.field.customer') }}</th>
                    <th class="text-right">{{ __('commission.field.base_amount') }}</th>
                    <th class="text-right">{{ __('commission.field.rate_percent') }}</th>
                    <th class="text-right">{{ __('commission.field.commission_amount') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr class="hover">
                    <td class="text-sm">{{ $row->earned_on?->format('d.m.Y') ?? '–' }}</td>
                    <td class="text-sm">{{ $row->user?->name ?? '–' }}</td>
                    <td class="font-mono text-sm">
                        {{ $row->invoice?->number ?? '–' }}
                        @if ($row->isReversal())
                            <x-status-badge tone="error" size="sm" outline>{{ __('commission.badge.reversal') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-sm text-base-content/70">{{ $row->invoice?->customer?->name ?? '–' }}</td>
                    <td class="text-right font-mono text-sm">{{ $row->base_amount?->format() ?? '–' }}</td>
                    <td class="text-right font-mono text-sm">{{ $row->rate_percent?->format() ?? '0,00' }} %</td>
                    <td class="text-right font-mono text-sm">{{ $row->commission_amount?->format() ?? '–' }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="7" :title="__('commission.empty.run_rows')" compact />
            @endforelse
        </x-table>
        <p class="mt-3 text-xs text-muted">{{ __('commission.hint.no_payout') }}</p>
    </x-card>
</x-page-shell>
@endsection
