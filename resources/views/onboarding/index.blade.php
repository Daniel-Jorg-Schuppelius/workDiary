@extends('layouts.app')

@section('title', __('Onboarding') . ' - ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Onboarding'))

@section('content')
<x-page-shell gap="6">
    <x-slot:toolbar>
        <x-page-toolbar
            :title="__('Onboarding-Checkliste')"
            :subtitle="$organization->name"
            :badge="sprintf('%d/%d', (int) $checklist['required_done'], (int) $checklist['required_total'])"
            badge-tone="primary"
        >
            {{ __('Pflichtschritte: :done von :total (:percent %)', [
                'done' => $checklist['required_done'],
                'total' => $checklist['required_total'],
                'percent' => $checklist['progress_percent'],
            ]) }}
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-['Space_Grotesk'] text-lg font-semibold text-base-content">{{ __('Fortschritt') }}</h2>
                <span class="badge {{ $checklist['all_required_done'] ? 'badge-success' : 'badge-primary' }} badge-outline">
                    {{ $checklist['progress_percent'] }} %
                </span>
            </div>
            <progress class="progress {{ $checklist['all_required_done'] ? 'progress-success' : 'progress-primary' }} w-full" value="{{ $checklist['progress_percent'] }}" max="100"></progress>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4">
        @foreach ($checklist['steps'] as $step)
            <article class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-icon :name="$step['done'] ? 'task_alt' : 'radio_button_unchecked'"
                                        class="{{ $step['done'] ? 'text-success' : 'text-base-content/50' }}" />
                                <h3 class="font-['Space_Grotesk'] text-base font-semibold text-base-content">{{ $step['title'] }}</h3>
                                @if ($step['required'])
                                    <span class="badge badge-sm badge-primary badge-outline">{{ __('Pflicht') }}</span>
                                @else
                                    <span class="badge badge-sm badge-ghost">{{ __('Empfohlen') }}</span>
                                @endif
                            </div>
                            @if (! empty($step['description']))
                                <p class="mt-1 text-sm text-base-content/70">{{ $step['description'] }}</p>
                            @endif
                        </div>

                        <span class="badge {{ $step['done'] ? 'badge-success' : 'badge-warning' }} badge-outline">
                            {{ $step['done'] ? __('Erledigt') : __('Offen') }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-3 border-t border-base-300/70 pt-3">
                        <span class="text-xs text-base-content/60">{{ $step['code'] }}</span>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            @if (! empty($step['href']) && ! empty($step['label']))
                                <a href="{{ $step['href'] }}" class="btn btn-sm btn-outline">{{ $step['label'] }}</a>
                            @endif
                            @if (! $step['done'] && $step['skippable'])
                                <form method="POST" action="{{ route('onboarding.steps.skip', ['step' => $step['code']]) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="text"
                                           name="reason"
                                           maxlength="1000"
                                           required
                                           class="input input-bordered input-sm w-64"
                                           placeholder="{{ __('Begründung für Überspringen') }}">
                                    <button type="submit" class="btn btn-sm btn-ghost text-warning">{{ __('Überspringen') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</x-page-shell>
@endsection
