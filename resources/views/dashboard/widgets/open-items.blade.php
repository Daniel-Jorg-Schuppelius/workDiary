{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : open-items.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Offene Posten" — Daten: OpenItemsWidget.
--}}
<x-card :title="__('Offene Posten')" icon="account_balance">
    <x-slot:actions>
        <x-button href="{{ route('finance.accounting.open-items.index') }}" tone="ghost" size="xs">{{ __('OPOS →') }}</x-button>
    </x-slot:actions>

    @if ($rows->isEmpty())
        <x-empty-state compact icon="check_circle"
                       :title="__('Nichts offen')" :message="__('Keine offenen Posten.')" />
    @else
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($rows as $row)
                <div class="rounded-box border border-base-300 bg-base-100 px-4 py-3 shadow-xs">
                    <p class="text-xs uppercase tracking-wider text-muted">{{ $row->direction->label() }}</p>
                    <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums">
                        {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row->total, 2, withThousandsSeparator: true) }} €
                    </p>
                    <p class="text-xs {{ (float) $row->overdue > 0 ? 'text-error' : 'text-muted' }}">
                        {{ __('Überfällig') }}: {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row->overdue, 2, withThousandsSeparator: true) }} €
                    </p>
                </div>
            @endforeach
        </div>
    @endif
</x-card>
