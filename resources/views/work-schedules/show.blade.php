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
    @php $s = $schedule ?? (object) $defaults; @endphp
    <ul class="rounded-box border border-base-300 bg-base-100 p-4 text-sm shadow-xs">
        <li>{{ __('Wochenstunden') }}: <strong>{{ intdiv((int)$s->weekly_minutes, 60) }}:{{ str_pad((string)($s->weekly_minutes % 60),2,'0',STR_PAD_LEFT) }} h</strong></li>
        <li>{{ __('Tagessoll') }}: <strong>{{ intdiv((int)$s->daily_target_minutes, 60) }}:{{ str_pad((string)($s->daily_target_minutes % 60),2,'0',STR_PAD_LEFT) }} h</strong></li>
        <li>{{ __('Kernzeit') }}: {{ substr((string)$s->core_start, 0, 5) }} – {{ substr((string)$s->core_end, 0, 5) }}</li>
        <li>{{ __('Rahmenzeit') }}: {{ substr((string)$s->frame_start, 0, 5) }} – {{ substr((string)$s->frame_end, 0, 5) }}</li>
        <li>{{ __('Pflichtpause') }}: {{ (int)$s->break_minutes }} min @ {{ (int)$s->break_after_minutes }} min</li>
    </ul>
</x-page-shell>
@endsection
