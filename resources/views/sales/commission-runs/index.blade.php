{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Provisions-Abrechnungsläufe (Feature 146): Entwurf = Vorschau,
  geschlossen = festgeschrieben.
--}}
@extends('layouts.app')
@section('title', __('commission.page.runs'))
@section('nav-title', __('commission.page.runs'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('commission.subtitle.runs')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('commission-runs.create')"
                        show-label>{{ __('commission.action.create_run') }}</x-icon-btn>
        @endif
        <x-icon-btn icon="receipt_long" tone="ghost" size="sm"
                    :href="route('commissions.index')"
                    show-label>{{ __('commission.action.to_commissions') }}</x-icon-btn>
        <x-icon-btn icon="percent" tone="ghost" size="sm"
                    :href="route('commission-rules.index')"
                    show-label>{{ __('commission.action.to_rules') }}</x-icon-btn>
    </x-slot:actions>

    <x-table scroll="flex" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string" default>{{ __('commission.field.period') }}</x-table.th>
                <x-table.th sort type="date">{{ __('commission.field.period_start') }}</x-table.th>
                <x-table.th sort type="date">{{ __('commission.field.period_end') }}</x-table.th>
                <x-table.th sort type="string" align="center">{{ __('commission.field.status') }}</x-table.th>
                <x-table.th sort type="number" align="center">{{ __('commission.field.entry_count') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('commission.field.total_commission') }}</x-table.th>
                <x-table.th sort type="string">{{ __('commission.field.closed_by') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($runs as $run)
            <tr class="hover">
                <td class="font-medium">
                    <a class="link link-hover" href="{{ route('commission-runs.show', $run) }}">{{ $run->period }}</a>
                </td>
                <td class="text-sm">{{ $run->period_start?->format('d.m.Y') }}</td>
                <td class="text-sm">{{ $run->period_end?->format('d.m.Y') }}</td>
                <td class="text-center">
                    <x-status-badge :tone="$run->status->tone()" size="sm">{{ $run->status->label() }}</x-status-badge>
                </td>
                <td class="text-center text-sm">{{ $run->isClosed() ? $run->entry_count : '–' }}</td>
                <td class="text-right font-mono text-sm">{{ $run->isClosed() ? ($run->total_commission?->format() ?? '–') : '–' }}</td>
                <td class="text-sm text-base-content/70">{{ $run->closer?->name ?? '–' }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="visibility" size="xs" :href="route('commission-runs.show', $run)" :label="__('commission.action.show')" />
                        <x-icon-btn icon="download" tone="ghost" size="xs" :href="route('commission-runs.export', $run)" :label="__('commission.action.export')" />
                        @if ($canManage && ! $run->isClosed())
                            <x-action-form :action="route('commission-runs.destroy', $run)" method="DELETE"
                                           :confirm="__('commission.confirm.delete_run')"
                                           confirm-icon="delete" confirm-tone="error"
                                           :confirm-label="__('commission.action.delete')">
                                <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('commission.action.delete')" />
                            </x-action-form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="event_repeat" :colspan="8" :title="__('commission.empty.runs')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$runs" standing />
</x-index-page>
@endsection
