@extends('layouts.app')

@section('title', __('Monatsfreigaben'))
@section('nav-title', __('Monatsfreigaben'))

@section('content')
    <x-index-page :subtitle="__('Eigene Monate prüfen und einreichen.')">
        <x-slot:actions>
            <x-icon-btn icon="calendar_month" tone="primary" size="sm"
                        :href="route('month-approval.show', ['year' => $defaultYear, 'month' => $defaultMonth])"
                        show-label>{{ __('Aktuellen Monat öffnen') }}</x-icon-btn>
        </x-slot:actions>

        @if ($closures->isEmpty())
            <x-empty-state framed
                icon='<span class="material-symbols-outlined" aria-hidden="true">calendar_month</span>'
                :title="__('Noch keine Monatsfreigaben')"
                :message="__('Sobald Sie einen Monat öffnen, wird automatisch eine Freigabe als Entwurf angelegt.')" />
        @else
            <x-table>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Periode') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Tage offen') }}</th>
                        <th class="text-right">{{ __('Warnungen') }}</th>
                        <th class="text-right">{{ __('Aktion') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($closures as $c)
                    <tr>
                        <td class="font-medium">{{ $c->periodLabel() }}</td>
                        <td>
                            <span class="badge badge-{{ $c->status->tone() }} badge-sm">{{ $c->status->label() }}</span>
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
    </x-index-page>
@endsection
