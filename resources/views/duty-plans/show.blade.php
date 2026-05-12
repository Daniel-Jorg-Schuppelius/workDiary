@extends('layouts.app')
@section('title', $dutyPlan->title)
@section('content')
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-6 overflow-auto">

    {{-- Header --}}
    @php
        $subtitle = __('duty_plan.period.' . $dutyPlan->period_type)
            . ' · ' . $dutyPlan->from_date->format('d.m.Y')
            . ' – ' . $dutyPlan->to_date->format('d.m.Y');
        if ($dutyPlan->min_staff > 0) {
            $subtitle .= ' · ' . __('Mindestbesetzung') . ': ' . $dutyPlan->min_staff;
        }
    @endphp
    <x-page-title
        :title="$dutyPlan->title"
        :subtitle="$subtitle"
        :badge="$dutyPlan->isPublished() ? __('duty_plan.status.published') : __('duty_plan.status.draft')"
        :badge-tone="$dutyPlan->isPublished() ? 'success' : 'ghost'"
    >
        <x-slot:actions>
            @can('update', $dutyPlan)
                @if ($dutyPlan->isDraft())
                    <form method="POST" action="{{ route('duty-plans.publish', $dutyPlan) }}">
                        @csrf @method('PATCH')
                        <button class="btn btn-success btn-sm">{{ __('Veröffentlichen') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('duty-plans.retract', $dutyPlan) }}">
                        @csrf @method('PATCH')
                        <button class="btn btn-warning btn-sm">{{ __('Zurück zu Entwurf') }}</button>
                    </form>
                @endif
                <a href="{{ route('duty-plans.edit', $dutyPlan) }}" data-entry-modal-trigger class="btn btn-ghost btn-sm">{{ __('Bearbeiten') }}</a>
            @endcan
            <a href="{{ route('duty-plans.index') }}" class="btn btn-ghost btn-sm">← {{ __('Übersicht') }}</a>
        </x-slot:actions>
    </x-page-title>

    @if ($dutyPlan->note)
        <div class="alert alert-info text-sm">{{ $dutyPlan->note }}</div>
    @endif

    {{-- Kalender-Raster --}}
    <div class="overflow-x-auto rounded-box border border-base-300">
        <table class="table table-sm w-full">
            <thead class="bg-base-200">
                <tr>
                    <th class="whitespace-nowrap">{{ __('Datum') }}</th>
                    <th>{{ __('Schichten') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($dates as $dateStr)
                    @php $dayShifts = $shiftsByDate->get($dateStr, collect()); @endphp
                    <tr class="{{ \Carbon\Carbon::parse($dateStr)->isWeekend() ? 'bg-base-200/50' : '' }}">
                        <td class="whitespace-nowrap font-medium w-32">
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
            </tbody>
        </table>
    </div>
</div>
@endsection
