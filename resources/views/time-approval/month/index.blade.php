{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Monatsfreigaben'))
@section('nav-title', __('Monatsfreigaben'))
@include('partials.page-fill')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Eigene Monate prüfen und einreichen.')">
        <x-slot:actions>
            <x-icon-btn icon="calendar_month" tone="primary" size="sm"
                        :href="route('month-approval.show', ['year' => $defaultYear, 'month' => $defaultMonth])"
                        show-label>{{ __('Aktuellen Monat öffnen') }}</x-icon-btn>
        </x-slot:actions>

        @if ($closures->total() === 0)
            <x-empty-state framed
                icon="calendar_month"
                :title="__('Noch keine Monatsfreigaben')"
                :message="__('Sobald Sie einen Monat öffnen, wird automatisch eine Freigabe als Entwurf angelegt.')" />
        @else
            <x-table scroll="flex" :pinRows="true" table-sort="server"
                     :route="route('month-approval.index')"
                     :current-sort="$sort"
                     :current-dir="$dir">
                <x-slot:head>
                    <tr>
                        <x-table.th sort="period" default>{{ __('Periode') }}</x-table.th>
                        <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                        <x-table.th sort="days_open" align="right">{{ __('Tage offen') }}</x-table.th>
                        <x-table.th sort="warnings" align="right">{{ __('Warnungen') }}</x-table.th>
                        <th class="text-right">{{ __('Aktion') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($closures as $c)
                    <tr class="hover">
                        <td class="font-medium">{{ $c->periodLabel() }}</td>
                        <td>
                            <x-status-badge :tone="$c->status->tone()" size="sm">{{ $c->status->label() }}</x-status-badge>
                        </td>
                        <td class="text-right tabular-nums">{{ $c->days_open }}</td>
                        <td class="text-right tabular-nums">{{ $c->warnings_count }}</td>
                        <td class="text-right">
                            <x-icon-btn icon="arrow_forward" size="sm" tone="ghost"
                                        :href="route('month-approval.show', ['year' => $c->period_year, 'month' => $c->period_month])"
                                        :aria-label="__('Öffnen')" />
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif

        <x-pagination :paginator="$closures" standing />
    </x-index-page>
@endsection
