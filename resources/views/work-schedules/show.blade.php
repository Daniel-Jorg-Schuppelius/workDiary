@extends('layouts.app')
@section('title', __('Mein Arbeitszeit-Modell'))
@section('nav-title', __('Mein Arbeitszeit-Modell'))
@section('content')
<x-page-shell>
    <x-page-toolbar :title="__('Mein Arbeitszeit-Modell')">
        @auth
            @can('create', \App\Models\WorkSchedule::class)
                <x-slot:actions>
                    <x-icon-btn icon="edit" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('users.work-schedule.edit', $user)"
                                show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                </x-slot:actions>
            @endcan
        @endauth
    </x-page-toolbar>
    @php
        $s = $schedule ?? (object) $defaults;
        $rawType = $s->schedule_type ?? 'flextime';
        $typeValue = $rawType instanceof \App\Enums\WorkSchedule\ScheduleType ? $rawType->value : (string) $rawType;
        $type = \App\Enums\WorkSchedule\ScheduleType::tryFrom($typeValue) ?? \App\Enums\WorkSchedule\ScheduleType::Flextime;
        $fmt = fn(int $m) => intdiv($m, 60) . ':' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT) . ' h';
        $weekdayLabels = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
        $dayTargets = (array) ($s->day_targets ?? []);
    @endphp
    <ul class="rounded-box border border-base-300 bg-base-100 p-4 text-sm shadow-xs space-y-1">
        <li>{{ __('Arbeitszeit-Typ') }}: <strong>{{ $type->label() }}</strong></li>

        @if ($type === \App\Enums\WorkSchedule\ScheduleType::Trust)
            <li class="text-base-content/70">{{ __('work_schedule.type_hint.trust') }}</li>
        @elseif ($type === \App\Enums\WorkSchedule\ScheduleType::PerWeekday)
            @foreach ($weekdayLabels as $iso => $lbl)
                @php $t = $dayTargets[$iso] ?? $dayTargets[(string) $iso] ?? null; @endphp
                @if ($t)
                    <li>{{ $lbl }}:
                        <strong>{{ $fmt((int) ($t['minutes'] ?? 0)) }}</strong>
                        @if (($t['mode'] ?? '') === 'times')
                            <span class="text-base-content/60">({{ substr((string) ($t['start'] ?? ''), 0, 5) }}–{{ substr((string) ($t['end'] ?? ''), 0, 5) }})</span>
                        @endif
                    </li>
                @endif
            @endforeach
            <li>{{ __('Wochenstunden') }}: <strong>{{ $fmt((int) $s->weekly_minutes) }}</strong></li>
        @else
            <li>{{ __('Wochenstunden') }}: <strong>{{ $fmt((int) $s->weekly_minutes) }}</strong></li>
            @if ($type === \App\Enums\WorkSchedule\ScheduleType::Flextime)
                <li>{{ __('Tagessoll') }}: <strong>{{ $fmt((int) $s->daily_target_minutes) }}</strong></li>
                <li>{{ __('Kernzeit') }}: {{ substr((string) $s->core_start, 0, 5) }} – {{ substr((string) $s->core_end, 0, 5) }}</li>
                <li>{{ __('Rahmenzeit') }}: {{ substr((string) $s->frame_start, 0, 5) }} – {{ substr((string) $s->frame_end, 0, 5) }}</li>
            @endif
        @endif

        <li>{{ __('Pflichtpause') }}: {{ (int) $s->break_minutes }} min @ {{ (int) $s->break_after_minutes }} min</li>
    </ul>
</x-page-shell>
@endsection
