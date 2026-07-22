@extends('layouts.app')
@section('title', $dutyPlan->title)
@section('nav-title', $dutyPlan->title)
@section('content')
<x-page-shell>

    <x-slot:toolbar>
        <x-page-toolbar :title="$dutyPlan->title"
                        :badge="$dutyPlan->isPublished() ? __('duty_plan.status.published') : __('duty_plan.status.draft')"
                        :badge-tone="$dutyPlan->isPublished() ? 'success' : 'ghost'">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('duty-plans.index')" show-label>{{ __('Übersicht') }}</x-icon-btn>
                <div class="dropdown dropdown-end">
                    <label tabindex="0" class="btn btn-outline btn-sm gap-1">
                        <x-icon name="print" /><span>{{ __('Drucken') }}</span>
                    </label>
                    <ul tabindex="0" class="menu dropdown-content z-1 mt-2 w-72 rounded-box bg-base-100 p-2 shadow">
                        <li class="menu-title">{{ __('Layout wählen') }}</li>
                        <li><a href="{{ route('print.duty-plan.roster', $dutyPlan) }}" target="_blank">{{ __('Monats-Aushang (A3 quer)') }}</a></li>
                        <li><a href="{{ route('print.duty-plan.week', $dutyPlan) }}" target="_blank">{{ __('Wochenplan (A4 quer)') }}</a></li>
                        <li><a href="{{ route('print.duty-plan.day', [$dutyPlan, 'date' => $dutyPlan->from_date->toDateString()]) }}" target="_blank">{{ __('Tagesbriefing (A4 hoch)') }}</a></li>
                        <li class="menu-title">{{ __('Datenschutz') }}</li>
                        <li><a href="{{ route('print.duty-plan.roster', [$dutyPlan, 'anonymous' => 1]) }}" target="_blank">{{ __('Aushang anonymisiert') }}</a></li>
                    </ul>
                </div>
                @can('view', $dutyPlan)
                    <x-icon-btn icon="shield_person" tone="outline" size="sm" :href="route('duty-plans.coverage.index', $dutyPlan)" show-label>{{ __('Soll-Besetzung') }}</x-icon-btn>
                @endcan
                @can('update', $dutyPlan)
                    <x-icon-btn icon="edit" size="sm"
                                data-entry-modal-trigger
                                :href="route('duty-plans.edit', $dutyPlan)"
                                show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    @if ($dutyPlan->isDraft())
                        <form method="POST" action="{{ route('duty-plans.publish', $dutyPlan) }}" class="inline">
                            @csrf @method('PATCH')
                            <x-icon-btn icon="publish" tone="success" size="sm" type="submit" show-label>{{ __('Veröffentlichen') }}</x-icon-btn>
                        </form>
                    @else
                        <form method="POST" action="{{ route('duty-plans.retract', $dutyPlan) }}" class="inline">
                            @csrf @method('PATCH')
                            <x-icon-btn icon="undo" tone="warning" size="sm" type="submit" show-label>{{ __('Zurück zu Entwurf') }}</x-icon-btn>
                        </form>
                    @endif
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($dutyPlan->note)
        <div class="alert alert-info text-sm">{{ $dutyPlan->note }}</div>
    @endif

    {{-- Soll/Ist-Heatmap --}}
    <details class="rounded-box border border-base-300 bg-base-100 p-3" open>
        <summary class="cursor-pointer font-semibold">{{ __('Soll/Ist-Besetzung') }}</summary>
        <div class="mt-3">
            @include('coverage-requirements._heatmap', ['dutyPlan' => $dutyPlan])
        </div>
    </details>

    {{-- Kalender-Raster --}}
    <div class="rounded-box border border-base-300">
        <x-table table-sort="client" bare>
            <x-slot:head>
                <tr class="bg-base-200">
                    <x-table.th sort type="date" class="whitespace-nowrap">{{ __('Datum') }}</x-table.th>
                    <th>{{ __('Schichten') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($dates as $dateStr)
                @php $dayShifts = $shiftsByDate->get($dateStr, collect()); @endphp
                <tr class="{{ \Carbon\Carbon::parse($dateStr)->isWeekend() ? 'bg-base-200/50' : '' }}">
                    <td class="whitespace-nowrap font-medium w-32" data-sort-value="{{ $dateStr }}">
                        <span class="text-base-content/60 text-xs">{{ \Carbon\Carbon::parse($dateStr)->isoFormat('dd') }}</span>
                        {{ \Carbon\Carbon::parse($dateStr)->format('d.m.') }}
                    </td>
                    <td>
                        @if ($dayShifts->isEmpty())
                            <span class="text-base-content/30 text-sm">–</span>
                        @else
                            <div class="flex flex-wrap gap-1">
                                @foreach ($dayShifts as $shift)
                                    <span class="badge badge-sm"
                                        @if ($shift->shiftType?->color)
                                            style="background-color:{{ $shift->shiftType->color }};color:#fff;"
                                        @endif
                                    >
                                        {{ $shift->user?->name ?? '–' }}
                                        @if ($shift->shiftType) ({{ $shift->shiftType->abbreviation }}) @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
    </div>
</x-page-shell>
@endsection
