{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : time-accounts.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Zeitkonten" — Daten: TimeAccountsWidget.
--}}
<x-card :title="__('Zeitkonten')" icon="account_balance_wallet">
    @if ($balances->isEmpty())
        <x-empty-state compact icon="account_balance_wallet"
                       :title="__('Keine Zeitkonten')" :message="__('Für dich ist noch kein Zeitkonto geführt.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($balances as $balance)
                <li class="flex items-center justify-between gap-3 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <span class="min-w-0 truncate">{{ $balance->account?->name ?? $balance->account?->code ?? '—' }}</span>
                    <span class="font-['Space_Grotesk'] font-semibold tabular-nums">
                        {{ $balance->account?->unit?->format((float) $balance->balance) ?? $balance->balance }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
