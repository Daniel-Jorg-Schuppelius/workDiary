{{-- Self-view dialog for WorkSchedule: editierbar nur mit work-schedule.manage
     (Personalverwaltung/Geschäftsführung/Admin), sonst read-only. --}}
@php
    $_canManage = auth()->check() && \Illuminate\Support\Facades\Gate::allows('create', \App\Models\WorkSchedule::class);
@endphp

@if ($_canManage)
    @include('work-schedules._form_dialog')
@else
    @php
        $s = $schedule ?? (object) $defaults;
        $rawType = $s->schedule_type ?? 'flextime';
        $typeValue = $rawType instanceof \App\Enums\WorkSchedule\ScheduleType ? $rawType->value : (string) $rawType;
        $type = \App\Enums\WorkSchedule\ScheduleType::tryFrom($typeValue) ?? \App\Enums\WorkSchedule\ScheduleType::Flextime;
        $fmt = fn(int $m) => intdiv($m, 60) . ':' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT) . ' h';
    @endphp
    <x-modal
        :title="__('Mein Arbeitszeit-Modell')"
        :eyebrow="$user->name"
        icon="schedule"
        tone="primary"
        hide-footer
    >
        <ul class="space-y-2 text-sm">
            <li class="flex justify-between gap-4">
                <span class="opacity-70">{{ __('Arbeitszeit-Typ') }}</span>
                <strong>{{ $type->label() }}</strong>
            </li>
            @if ($type === \App\Enums\WorkSchedule\ScheduleType::Trust)
                <li class="text-xs opacity-70">{{ __('work_schedule.type_hint.trust') }}</li>
            @else
                <li class="flex justify-between gap-4">
                    <span class="opacity-70">{{ __('Wochenstunden') }}</span>
                    <strong>{{ $fmt((int) $s->weekly_minutes) }}</strong>
                </li>
                @if ($type === \App\Enums\WorkSchedule\ScheduleType::Flextime)
                    <li class="flex justify-between gap-4">
                        <span class="opacity-70">{{ __('Tagessoll') }}</span>
                        <strong>{{ $fmt((int) $s->daily_target_minutes) }}</strong>
                    </li>
                    <li class="flex justify-between gap-4">
                        <span class="opacity-70">{{ __('Kernzeit') }}</span>
                        <span>{{ substr((string) $s->core_start, 0, 5) }} – {{ substr((string) $s->core_end, 0, 5) }}</span>
                    </li>
                    <li class="flex justify-between gap-4">
                        <span class="opacity-70">{{ __('Rahmenzeit') }}</span>
                        <span>{{ substr((string) $s->frame_start, 0, 5) }} – {{ substr((string) $s->frame_end, 0, 5) }}</span>
                    </li>
                @endif
            @endif
            <li class="flex justify-between gap-4">
                <span class="opacity-70">{{ __('Pflichtpause') }}</span>
                <span>{{ (int) $s->break_minutes }} min @ {{ (int) $s->break_after_minutes }} min</span>
            </li>
        </ul>
        <p class="mt-4 text-xs opacity-60">{{ __('Änderungen am Arbeitszeit-Modell können nur die Personalverwaltung oder Geschäftsführung vornehmen.') }}</p>
    </x-modal>
@endif
