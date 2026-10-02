{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : personal-onboarding.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kachel „Mein Einstieg“ (MVP-911): Fortschritt und die nächsten offenen Schritte. --}}
@php $open = array_slice(array_values(array_filter($checklist['steps'], static fn (array $s): bool => ! $s['done'])), 0, 3); @endphp
<section class="rounded-box border border-primary/40 bg-[color-mix(in_oklab,var(--color-primary)_5%,var(--color-base-100))] p-5 shadow-xs">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="font-semibold">{{ __('onboarding.personal.title') }}</h2>
            <p class="text-sm text-base-content/70">{{ __('onboarding.personal.progress', ['done' => $checklist['done'], 'total' => $checklist['total']]) }}</p>
        </div>
        <x-button :href="route('me.onboarding')" tone="primary" size="sm">{{ __('onboarding.personal.open') }}</x-button>
    </header>
    <progress class="progress progress-primary mt-3 w-full" value="{{ $checklist['percent'] }}" max="100" aria-label="{{ __('onboarding.personal.title') }}"></progress>
    <ul class="mt-3 space-y-1 text-sm">
        @foreach ($open as $step)
            <li><a class="link link-hover" href="{{ $step['url'] }}">{{ __('onboarding.personal.step.' . $step['code'] . '.title') }}</a></li>
        @endforeach
    </ul>
</section>
