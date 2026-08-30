{{--
  Created on   : Sun May 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('onboarding.page.title') . ' - ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('onboarding.page.title'))

@section('content')
<x-index-page
    :title="__('onboarding.page.heading')"
    :subtitle="$organization->name"
    :badge="sprintf('%d/%d', (int) $checklist['required_done'], (int) $checklist['required_total'])"
    badge-tone="primary"
>
    <x-slot:note>{{ __('onboarding.page.progress_summary', [
        'done' => $checklist['required_done'],
        'total' => $checklist['required_total'],
        'percent' => $checklist['progress_percent'],
    ]) }}</x-slot:note>
    <x-slot:actions>
        <x-help-button topic="onboarding.checklist" />
        @if (empty($widgetDismissedAt))
            <form method="POST" action="{{ route('onboarding.widget.dismiss') }}">
                @csrf
                <x-button type="submit" tone="ghost" size="sm" class="text-base-content/70">{{ __('onboarding.widget.dismiss') }}</x-button>
            </form>
        @else
            <span class="text-xs text-muted">
                {{ __('onboarding.widget.dismissed_at', ['date' => $widgetDismissedAt]) }}
            </span>
        @endif
    </x-slot:actions>

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-['Space_Grotesk'] text-lg font-semibold text-base-content">{{ __('onboarding.page.progress_label') }}</h2>
                <span class="badge {{ $checklist['all_required_done'] ? 'badge-success' : 'badge-primary' }} badge-outline">
                    {{ $checklist['progress_percent'] }} %
                </span>
            </div>
            <progress class="progress {{ $checklist['all_required_done'] ? 'progress-success' : 'progress-primary' }} w-full" value="{{ $checklist['progress_percent'] }}" max="100"></progress>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4">
        @foreach ($checklist['steps'] as $step)
            @php
                $stateBadge = match ($step['state']) {
                    'done' => ['class' => 'badge-success', 'label' => __('onboarding.page.badge_done')],
                    'skipped' => ['class' => 'badge-ghost', 'label' => __('onboarding.page.badge_skipped')],
                    default => ['class' => 'badge-warning', 'label' => __('onboarding.page.badge_open')],
                };
            @endphp
            <article class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-icon :name="$step['done'] ? 'task_alt' : 'radio_button_unchecked'"
                                        class="{{ $step['done'] ? 'text-success' : 'text-muted' }}" />
                                <h3 class="font-['Space_Grotesk'] text-base font-semibold text-base-content">{{ $step['title'] }}</h3>
                                @if ($step['required'])
                                    <x-status-badge tone="primary" size="sm" outline>{{ __('onboarding.page.badge_required') }}</x-status-badge>
                                @else
                                    <x-status-badge tone="ghost" size="sm">{{ __('onboarding.page.badge_recommended') }}</x-status-badge>
                                @endif
                            </div>
                            @if (! empty($step['description']))
                                <p class="mt-1 text-sm text-base-content/70">{{ $step['description'] }}</p>
                            @endif
                            @if ($step['state'] === 'skipped' && ! empty($step['skipped_reason']))
                                <p class="mt-1 text-xs text-muted">
                                    {{ __('onboarding.page.badge_skipped') }}: {{ $step['skipped_reason'] }}
                                </p>
                            @endif
                        </div>

                        <span class="badge {{ $stateBadge['class'] }} badge-outline">
                            {{ $stateBadge['label'] }}
                        </span>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-base-300/70 pt-3">
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            @if (! empty($step['href']) && ! empty($step['label']))
                                <x-button href="{{ $step['href'] }}" tone="outline" size="sm">{{ $step['label'] }}</x-button>
                            @endif
                            @if (! $step['done'] && $step['skippable'])
                                <form method="POST" action="{{ route('onboarding.steps.skip', ['step' => $step['code']]) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input aria-label="{{ __('onboarding.action.skip_placeholder') }}" type="text"
                                           name="reason"
                                           maxlength="1000"
                                           required
                                           class="input input-bordered input-sm w-64"
                                           placeholder="{{ __('onboarding.action.skip_placeholder') }}">
                                    <x-button type="submit" tone="ghost" size="sm" class="text-warning">{{ __('onboarding.action.skip') }}</x-button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</x-index-page>
@endsection
