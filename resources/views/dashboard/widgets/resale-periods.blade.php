{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : resale-periods.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Abos & Lizenzen" — Daten: ResalePeriodsWidget ($openAmount: offener
  Betrag je Währung, fertig formatiert).
--}}
<x-card :title="__('resale.widget.title')" icon="subscriptions">
    <x-slot:actions>
        <x-button href="{{ route('finance.resale.periods.index') }}" tone="ghost" size="xs">{{ __('resale.periods.title') }} →</x-button>
    </x-slot:actions>

    @if ($open === 0 && $proposed === 0 && $unassigned === 0)
        <x-empty-state compact icon="check_circle" :title="__('resale.widget.all_clear')" :message="__('resale.widget.all_clear_hint')" />
    @else
        <div class="grid gap-3 sm:grid-cols-3">
            <x-card as="a" padding="px-4 py-3" class="hover:bg-base-200" href="{{ route('finance.resale.periods.index') }}">
                <p class="text-xs uppercase tracking-wider text-muted">{{ __('resale.widget.open') }}</p>
                <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums {{ $open > 0 ? 'text-error' : '' }}">{{ $open }}</p>
                <p class="text-xs text-muted">{{ $openAmount !== '' ? $openAmount : '—' }}</p>
            </x-card>
            <x-card as="a" padding="px-4 py-3" class="hover:bg-base-200" href="{{ route('finance.resale.periods.index') }}">
                <p class="text-xs uppercase tracking-wider text-muted">{{ __('resale.widget.proposed') }}</p>
                <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums {{ $proposed > 0 ? 'text-info' : '' }}">{{ $proposed }}</p>
                <p class="text-xs text-muted">{{ __('resale.link.proposed_hint') }}</p>
            </x-card>
            @can(\App\Enums\User\Permission::ResellingManage->value)
                <x-card as="a" padding="px-4 py-3" class="hover:bg-base-200" href="{{ route('finance.resale.inbox') }}">
                    <p class="text-xs uppercase tracking-wider text-muted">{{ __('resale.summary.unassigned') }}</p>
                    <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums {{ $unassigned > 0 ? 'text-warning' : '' }}">{{ $unassigned }}</p>
                    <p class="text-xs text-muted">{{ __('resale.inbox.title') }}</p>
                </x-card>
            @endcan
        </div>
    @endif
</x-card>
