{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : approvals.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Offene Genehmigungen" — Daten: ApprovalsWidget.
--}}
<x-card :title="__('Offene Genehmigungen')" icon="rule">
    <div class="grid gap-3 sm:grid-cols-2">
        <a href="{{ route('expense-approvals.inbox') }}"
           class="rounded-box border border-base-300 bg-base-100 px-4 py-3 shadow-xs transition hover:border-primary">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Spesen') }}</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums {{ $pending['expenses'] > 0 ? 'text-warning' : '' }}">
                {{ $pending['expenses'] }}
            </p>
        </a>
        <a href="{{ route('vacations.index', ['status' => 'pending']) }}"
           class="rounded-box border border-base-300 bg-base-100 px-4 py-3 shadow-xs transition hover:border-primary">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Urlaub') }}</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums {{ $pending['vacations'] > 0 ? 'text-info' : '' }}">
                {{ $pending['vacations'] }}
            </p>
        </a>
    </div>
</x-card>
