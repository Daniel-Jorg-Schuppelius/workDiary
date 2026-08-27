{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : safety-due.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Arbeitsschutz-Fristen" — Daten: SafetyDueWidget.
--}}
<x-card :title="__('Arbeitsschutz-Fristen')" icon="health_and_safety">
    <div class="grid gap-3 sm:grid-cols-2">
        <a href="{{ route('safety.assessments.index') }}"
           class="rounded-box border {{ $assessmentsOverdue > 0 ? 'border-error/40 bg-error/5' : 'border-base-300 bg-base-100' }} px-4 py-3 shadow-xs transition hover:border-primary">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Gefährdungsbeurteilungen') }}</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums">{{ $assessmentsDue }}</p>
            <p class="text-xs text-muted">{{ __(':n überfällig', ['n' => $assessmentsOverdue]) }}</p>
        </a>
        <a href="{{ route('safety.checkups.index') }}"
           class="rounded-box border {{ $checkupsOverdue > 0 ? 'border-error/40 bg-error/5' : 'border-base-300 bg-base-100' }} px-4 py-3 shadow-xs transition hover:border-primary">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Vorsorge') }}</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums">{{ $checkupsDue }}</p>
            <p class="text-xs text-muted">{{ __(':n überfällig', ['n' => $checkupsOverdue]) }}</p>
        </a>
    </div>
</x-card>
