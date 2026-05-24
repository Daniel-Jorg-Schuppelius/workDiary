@props([
    'checklist' => null,
    'widgetDismissedAt' => null,
])

@php
    $hide = $checklist === null
        || ! empty($widgetDismissedAt);
@endphp

@unless ($hide)
    @php
        $allDone = (bool) ($checklist['all_required_done'] ?? false);
        $percent = (int) ($checklist['progress_percent'] ?? 0);
        $required_done = (int) ($checklist['required_done'] ?? 0);
        $required_total = (int) ($checklist['required_total'] ?? 0);
        $openSteps = array_values(array_filter(
            $checklist['steps'] ?? [],
            static fn(array $step): bool => ! ($step['done'] ?? false) && ($step['state'] ?? 'open') !== 'skipped'
        ));
    @endphp

    <section class="rounded-box border {{ $allDone ? 'border-success/40 bg-success/5' : 'border-primary/40 bg-primary/5' }} p-6 shadow-xs">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-['Space_Grotesk'] text-lg font-semibold text-base-content">
                    {{ $allDone ? __('onboarding.widget.complete_headline') : __('onboarding.widget.title') }}
                </h2>
                <p class="text-sm text-base-content/70">
                    {{ $allDone
                        ? __('onboarding.widget.complete_subtitle')
                        : __('onboarding.widget.subtitle', ['done' => $required_done, 'total' => $required_total]) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('onboarding.index') }}" class="btn btn-sm btn-primary">
                    {{ __('onboarding.widget.open_link') }}
                </a>
                @can(\App\Enums\User\Permission::OrgOnboardingDismissWidget->value)
                    <form method="POST" action="{{ route('onboarding.widget.dismiss') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-ghost text-base-content/70">
                            {{ __('onboarding.widget.dismiss') }}
                        </button>
                    </form>
                @endcan
            </div>
        </header>

        <div class="mt-3">
            <progress class="progress {{ $allDone ? 'progress-success' : 'progress-primary' }} w-full"
                      value="{{ $percent }}"
                      max="100"></progress>
        </div>

        @unless ($allDone)
            <details class="collapse collapse-arrow mt-3 border border-base-300/70 bg-base-100">
                <summary class="collapse-title text-sm font-medium">
                    {{ trans_choice('onboarding.widget.open_steps', count($openSteps), ['count' => count($openSteps)]) }}
                </summary>
                <div class="collapse-content">
                    <ul class="space-y-1 text-sm">
                        @foreach ($openSteps as $step)
                            <li class="flex items-center gap-2">
                                <x-icon name="radio_button_unchecked" class="text-base-content/50" />
                                <span>{{ $step['title'] }}</span>
                                @if ($step['required'])
                                    <span class="badge badge-xs badge-primary badge-outline">{{ __('onboarding.page.badge_required') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </details>
        @endunless
    </section>
@endunless
