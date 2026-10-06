{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _secure_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Einbehalt durch die Bürgschaft ablösen (Feature 114, MVP-603 ↔ MVP-602).
  Ohne passenden Einbehalt bleibt der Dialog ohne Formular.
--}}
<x-modal
    :title="__('guarantee.secure.title')"
    :eyebrow="$guarantee->reference ?? __('guarantee.title')"
    icon="savings"
    :action="$retentions->isEmpty() ? null : route('guarantees.secure', $guarantee)"
    method="POST"
    :submit-label="__('guarantee.secure.submit')"
>
    <p class="text-sm text-base-content/70">{{ __('guarantee.secure.hint') }}</p>

    @if ($retentions->isEmpty())
        <x-empty-state icon="savings" :title="__('guarantee.secure.empty')" compact />
    @else
        <x-select-field name="retention" :label="__('guarantee.secure.retention')" required>
            @foreach ($retentions as $retention)
                <option value="{{ $retention->sqid }}" @selected(old('retention') === $retention->sqid)>
                    {{ $retention->invoice?->number ?? '—' }}
                    · {{ $retention->invoice?->customer?->displayLabel() ?? '—' }}
                    · {{ $retention->kind->label() }}
                    · {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($retention->amount->toFloat(), 2, withThousandsSeparator: true) }} {{ $retention->amount->getCurrency()->value }}
                    @if ($retention->due_on !== null)
                        · {{ $retention->due_on->fdate() }}
                    @endif
                </option>
            @endforeach
        </x-select-field>
    @endif
</x-modal>
