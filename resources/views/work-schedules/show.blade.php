@extends('layouts.app')
@section('title', __('Mein Arbeitszeit-Modell'))
@section('content')
<div class="w-full p-4 space-y-4">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="font-['Space_Grotesk'] text-xl font-semibold">{{ __('Mein Arbeitszeit-Modell') }}</h1>
        @auth
            @if (auth()->user()->isAdmin())
                <a href="{{ route('users.work-schedule.edit', $user) }}" data-entry-modal-trigger
                   class="btn btn-sm btn-primary">
                    <x-icon name="edit" size="sm" /> {{ __('Bearbeiten') }}
                </a>
            @endif
        @endauth
    </div>
    @php $s = $schedule ?? (object) $defaults; @endphp
    <ul class="rounded-box border border-base-300 bg-base-100 p-4 text-sm shadow-xs">
        <li>{{ __('Wochenstunden') }}: <strong>{{ intdiv((int)$s->weekly_minutes, 60) }}:{{ str_pad((string)($s->weekly_minutes % 60),2,'0',STR_PAD_LEFT) }} h</strong></li>
        <li>{{ __('Tagessoll') }}: <strong>{{ intdiv((int)$s->daily_target_minutes, 60) }}:{{ str_pad((string)($s->daily_target_minutes % 60),2,'0',STR_PAD_LEFT) }} h</strong></li>
        <li>{{ __('Kernzeit') }}: {{ substr((string)$s->core_start, 0, 5) }} – {{ substr((string)$s->core_end, 0, 5) }}</li>
        <li>{{ __('Rahmenzeit') }}: {{ substr((string)$s->frame_start, 0, 5) }} – {{ substr((string)$s->frame_end, 0, 5) }}</li>
        <li>{{ __('Pflichtpause') }}: {{ (int)$s->break_minutes }} min @ {{ (int)$s->break_after_minutes }} min</li>
    </ul>
</div>
@endsection
