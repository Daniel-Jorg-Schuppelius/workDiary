@extends('layouts.app')

@section('title', __('Arbeitsbilanz'))
@section('nav-title', __('Arbeitsbilanz') . ' — ' . $label)

@php
    $fmt = function (int $minutes): string {
        $sign = $minutes < 0 ? '-' : '';
        $m = abs($minutes);
        return $sign . sprintf('%d:%02d', intdiv($m, 60), $m % 60);
    };
@endphp

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('Soll-Ist-Vergleich von Anwesenheit, erfasster Zeit und Saldo für :user.', ['user' => $user->name])">
                <x-slot:actions>
                    <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                                :href="route('reports.work-balance', array_merge(request()->query(), ['export' => 'pdf']))"
                                show-label>PDF</x-icon-btn>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        @if (! empty($selectableUsers))
            <x-filter-bar :action="route('reports.work-balance')" :reset="route('reports.work-balance')">
                @foreach (request()->except(['user', 'export']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <select name="user" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('Nutzer') }}" onchange="this.form.submit()">
                    @foreach ($selectableUsers as $u)
                        <option value="{{ $u->id }}" @selected((int) $u->id === (int) $user->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </x-filter-bar>
        @endif

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Soll') }}</div>
                <div class="text-2xl font-semibold">{{ $fmt($period->targetMinutes) }} h</div>
            </div>
            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Anwesenheit') }}</div>
                <div class="text-2xl font-semibold">{{ $fmt($period->attendanceMinutes) }} h</div>
            </div>
            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Erfasst') }}</div>
                <div class="text-2xl font-semibold">{{ $fmt($period->trackedMinutes) }} h</div>
            </div>
            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Unverteilt') }}</div>
                <div class="text-2xl font-semibold">{{ $fmt($period->untrackedMinutes) }} h</div>
            </div>
            <div class="rounded-box border bg-base-100 p-3 {{ $period->balanceMinutes >= 0 ? 'border-success/40' : 'border-error/40' }}">
                <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Saldo') }}</div>
                <div class="text-2xl font-semibold {{ $period->balanceMinutes >= 0 ? 'text-success' : 'text-error' }}">
                    {{ $fmt($period->balanceMinutes) }} h
                </div>
            </div>
        </div>

        @if (! empty($period->byActivity))
            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                <div class="mb-2 text-xs uppercase tracking-wider text-base-content/60">{{ __('Verteilung nach Tätigkeit') }}</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($period->byActivity as $type => $minutes)
                        <span class="badge badge-outline gap-2 px-3 py-3">
                            <strong>{{ \App\Models\TimeEntry::activityLabel($type) }}</strong>
                            <span>{{ $fmt((int) $minutes) }} h</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <x-table table-sort="client" :zebra="false">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="date">{{ __('Datum') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Soll') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Anwesenheit') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Pause') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Erfasst') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Unverteilt') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Saldo') }}</x-table.th>
                </tr>
            </x-slot:head>
            <x-slot:foot>
                <tr class="font-semibold">
                    <td>{{ __('Summe') }}</td>
                    <td class="text-right">{{ $fmt($period->targetMinutes) }}</td>
                    <td class="text-right">{{ $fmt($period->attendanceMinutes) }}</td>
                    <td class="text-right">{{ $fmt($period->breakMinutes) }}</td>
                    <td class="text-right">{{ $fmt($period->trackedMinutes) }}</td>
                    <td class="text-right">{{ $fmt($period->untrackedMinutes) }}</td>
                    <td class="text-right {{ $period->balanceMinutes >= 0 ? 'text-success' : 'text-error' }}">
                        {{ $fmt($period->balanceMinutes) }}
                    </td>
                </tr>
            </x-slot:foot>
            @foreach ($period->days as $day)
                @if ($day->targetMinutes === 0 && $day->attendanceMinutes === 0 && $day->trackedMinutes === 0)
                    @continue
                @endif
                <tr>
                    <td class="font-mono" data-sort-value="{{ \Carbon\Carbon::parse($day->date)->format('Y-m-d') }}">{{ \Carbon\Carbon::parse($day->date)->format('D, d.m.Y') }}</td>
                    <td class="text-right" data-sort-value="{{ (int) $day->targetMinutes }}">{{ $fmt($day->targetMinutes) }}</td>
                    <td class="text-right" data-sort-value="{{ (int) $day->attendanceMinutes }}">{{ $fmt($day->attendanceMinutes) }}</td>
                    <td class="text-right text-base-content/60" data-sort-value="{{ (int) $day->breakMinutes }}">{{ $fmt($day->breakMinutes) }}</td>
                    <td class="text-right" data-sort-value="{{ (int) $day->trackedMinutes }}">{{ $fmt($day->trackedMinutes) }}</td>
                    <td class="text-right text-warning" data-sort-value="{{ (int) $day->untrackedMinutes }}">{{ $fmt($day->untrackedMinutes) }}</td>
                    <td class="text-right {{ $day->balanceMinutes >= 0 ? 'text-success' : 'text-error' }}" data-sort-value="{{ (int) $day->balanceMinutes }}">
                        {{ $fmt($day->balanceMinutes) }}
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-page-shell>
@endsection
