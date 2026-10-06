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
        <x-card as="a" padding="px-4 py-3" class="transition hover:border-primary" href="{{ route('expense-approvals.inbox') }}">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Spesen') }}</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums {{ $pending['expenses'] > 0 ? 'text-warning' : '' }}">
                {{ $pending['expenses'] }}
            </p>
        </x-card>
        <x-card as="a" padding="px-4 py-3" class="transition hover:border-primary" href="{{ route('vacations.index', ['status' => 'pending']) }}">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Urlaub') }}</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums {{ $pending['vacations'] > 0 ? 'text-info' : '' }}">
                {{ $pending['vacations'] }}
            </p>
        </x-card>
    </div>
</x-card>
