{{--
  Created on   : Mon May 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : onboarding.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-card :title="__('Onboarding')">
    @if (! empty($onboarding) && isset($onboarding['checklist']))
        @php
            $checklist = $onboarding['checklist'];
            $total = is_array($checklist['items'] ?? null) ? count($checklist['items']) : 0;
            $done = is_array($checklist['items'] ?? null)
                ? count(array_filter($checklist['items'], fn ($i) => is_array($i) && ! empty($i['completed'])))
                : 0;
            $percent = $total > 0 ? (int) round($done / $total * 100) : 0;
        @endphp

        <div class="mb-2 flex items-center justify-between text-sm">
            <span class="text-base-content/70">{{ __(':done von :total erledigt', ['done' => $done, 'total' => $total]) }}</span>
            <span class="font-semibold">{{ $percent }}%</span>
        </div>
        <progress class="progress progress-primary w-full" value="{{ $percent }}" max="100"></progress>
    @else
        <p class="text-sm text-base-content/60">{{ __('Keine Onboarding-Daten verfügbar.') }}</p>
    @endif
</x-card>
