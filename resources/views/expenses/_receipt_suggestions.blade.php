{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _receipt_suggestions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vorschlagsliste Auslage ↔ Buchhaltungsbeleg (Feature 105, MVP-551) — von
  beiden Zweigen des Beleg-Dialogs genutzt (mit und ohne Push-Angebot).
  Variablen: $suggestions, $expense, $canLink
--}}
<p class="text-sm text-base-content/70">{{ __('expenses.receipt.suggestions_hint') }}</p>
<ul class="mt-2 divide-y divide-base-300 text-sm">
    @foreach ($suggestions as $candidate)
        <li class="flex items-center justify-between gap-2 py-2">
            <div class="min-w-0">
                <span class="font-medium">{{ $candidate->voucher_number ?: '—' }}</span>
                <span class="text-base-content/60">
                    · {{ optional($candidate->voucher_date)->format('d.m.Y') }}
                    · {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($candidate->total_amount?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }}
                    {{ $candidate->currency->value }}
                    @if ($candidate->supplier)
                        · {{ $candidate->supplier->name }}
                    @endif
                </span>
            </div>
            @if ($canLink)
                <x-action-form :action="route('expenses.link-voucher', $expense)">
                    <input type="hidden" name="voucher" value="{{ $candidate->sqid }}">
                    <x-icon-btn icon="link" tone="primary" size="sm" type="submit"
                                :label="__('expenses.receipt.link')" />
                </x-action-form>
            @endif
        </li>
    @endforeach
</ul>
