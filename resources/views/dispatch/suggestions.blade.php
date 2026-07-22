@extends('layouts.app')

@section('title', __('Leerzeit-Vorschläge'))
@section('nav-title', __('Leerzeit-Vorschläge'))

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-slot:toolbar>
        <x-page-toolbar :title="__('Leerzeit-/Lückenfüller-Vorschläge')">
            <div class="text-sm text-base-content/70">{{ __('Freie Slots aus Schichten, Touren und Disposition — Übernahme bleibt eine bewusste Entscheidung. Keine Standortüberwachung: nur Planungsdaten.') }}</div>
            <x-slot:actions>
                <x-icon-btn icon="dashboard" size="sm" :href="route('dispatch.board')" show-label>{{ __('Dispo-Board') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('dispatch.suggestions')" :reset="route('dispatch.suggestions')">
        <select name="user_id" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('Mitarbeiter') }}">
            <option value="">{{ __('Mitarbeiter wählen …') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected($selected !== null && $selected->id === $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <input type="date" name="date" value="{{ $date->toDateString() }}" class="input input-sm input-bordered shrink-0" aria-label="{{ __('Datum') }}">
    </x-filter-bar>

    @if ($selected === null)
        <x-empty-state icon="person_search" :title="__('Mitarbeiter und Tag wählen, um freie Slots und Vorschläge zu sehen.')" />
    @else
        <x-card :title="__('Freie Slots am :date (:name)', ['date' => $date->fdate(), 'name' => $selected->name])">
            @if ($slots === [])
                <p class="text-sm text-base-content/60">{{ __('Keine freien Slots — der Tag ist voll belegt oder es gibt kein Arbeitsfenster.') }}</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($slots as $slot)
                        <span class="badge badge-outline">{{ $slot['start'] }}–{{ $slot['end'] }} ({{ $slot['net_minutes'] }} {{ __('Min.') }})</span>
                    @endforeach
                </div>
            @endif
        </x-card>

        @if ($suggestions === [])
            <x-empty-state icon="task_alt" :title="__('Keine passenden Aufträge für die freien Slots gefunden.')" />
        @else
            <div class="space-y-3">
                @foreach ($suggestions as $suggestion)
                    <x-card>
                        <div class="flex flex-wrap items-center gap-2">
                            <a class="link font-medium" href="{{ route('diary.show', $suggestion['entry']) }}">{{ $suggestion['entry']->title ?? __('Auftrag #:id', ['id' => $suggestion['entry']->id]) }}</a>
                            <x-status-badge size="xs" outline>{{ __('Score :score', ['score' => $suggestion['score']]) }}</x-status-badge>
                            @if ($suggestion['distance_is_estimate'] && $suggestion['distance_km'] !== null)
                                <span class="badge badge-warning badge-xs">{{ __('grobe Schätzung (Luftlinie)') }}</span>
                            @endif
                        </div>
                        <ul class="mt-1 list-disc pl-5 text-sm text-base-content/80">
                            @foreach ($suggestion['reasons'] as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                        @foreach ($suggestion['warnings'] as $warning)
                            <p class="mt-1 text-sm text-warning">⚠ {{ $warning }}</p>
                        @endforeach
                        @can('viewAny', \App\Models\DiaryEntry::class)
                            <div class="mt-2 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('dispatch.suggestions.apply', $suggestion['entry']) }}" class="flex flex-wrap items-center gap-1">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $selected->sqid }}">
                                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                                    <input type="hidden" name="duration" value="{{ $suggestion['duration_minutes'] }}">
                                    <input type="time" name="start" value="{{ $suggestion['slot']['start'] }}" class="input input-xs input-bordered" aria-label="{{ __('Start') }}">
                                    <button type="submit" class="btn btn-xs btn-primary">{{ __('Übernehmen') }}</button>
                                </form>
                                <form method="POST" action="{{ route('dispatch.suggestions.dismiss', $suggestion['entry']) }}" class="flex flex-wrap items-center gap-1">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $selected->sqid }}">
                                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                                    <input name="reason" maxlength="500" class="input input-xs input-bordered w-48" placeholder="{{ __('Grund (optional)') }}">
                                    <button type="submit" class="btn btn-xs btn-outline">{{ __('Ablehnen') }}</button>
                                </form>
                            </div>
                        @endcan
                    </x-card>
                @endforeach
            </div>
        @endif
    @endif
</x-page-shell>
@endsection
