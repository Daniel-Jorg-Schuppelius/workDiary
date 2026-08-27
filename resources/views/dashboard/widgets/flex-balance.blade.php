{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : flex-balance.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Arbeitszeitkonto" — Daten: FlexBalanceWidget.
--}}
<x-card :title="__('Arbeitszeitkonto')" icon="balance">
    <x-slot:actions>
        <x-button href="{{ route('flex.index') }}" tone="ghost" size="xs">{{ __('Alle →') }}</x-button>
    </x-slot:actions>

    @if ($balance === null)
        <x-empty-state compact icon="balance"
                       :title="__('Noch kein Saldo')" :message="__('Sobald ein Monat abgerechnet ist, erscheint der Saldo hier.')" />
    @else
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-wider text-muted">{{ __('Saldo') }}</p>
                <p @class([
                    "mt-1 font-['Space_Grotesk'] text-3xl font-bold tabular-nums",
                    'text-success' => $tone === 'success',
                    'text-warning' => $tone === 'warning',
                    'text-error' => $tone === 'error',
                ])>
                    {{ $balance->balance_minutes >= 0 ? '+' : '' }}{{ \App\Support\Formats::duration($balance->balance_minutes) }}
                </p>
            </div>
            <div class="text-right text-xs text-muted">
                <p>{{ \Carbon\CarbonImmutable::createFromDate($balance->year, $balance->month, 1)->translatedFormat('F Y') }}</p>
                <p>{{ __('Soll') }}: {{ \App\Support\Formats::duration($balance->target_minutes) }}</p>
                <p>{{ __('Ist') }}: {{ \App\Support\Formats::duration($balance->actual_minutes) }}</p>
            </div>
        </div>
    @endif
</x-card>
