{{--
  Created on   : Sat Oct 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _holder_picker.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Halterwahl: Modus, Kunde, Fremdkunde des Kunden (Alpine `resaleHolderPicker`)
  — eine Stelle für Zuordnung, Sammelzuordnung, Aufteilung, Abtretung und
  Übernahme (Konsolidierungs-Audit 2026-11-02, kon-11). Erwartet $customers,
  $foreignByCustomer (ProvidesHolderPicker), $mode (gewählter Modus, schon mit
  old()) und $customerDefault (Sqid oder leer); optional $modes (angebotene
  Modi, Standard customer|foreign), $foreignDefault, $modeLabel, $modeHint und
  $modeHints (Modus → Hinweis unter der Auswahl). Ohne Modus „own“ ist der
  Kunde Pflicht.
--}}
@php
    $modes = $modes ?? ['customer', 'foreign'];
    $foreignDefault = $foreignDefault ?? '';
    $modeLabel = $modeLabel ?? __('resale.transfer.mode');
    $modeHint = $modeHint ?? null;
    $modeHints = $modeHints ?? [];
    $modeLabels = [
        'customer' => __('resale.inbox.mode_customer'),
        'partner' => __('resale.inbox.mode_partner'),
        'foreign' => __('resale.inbox.mode_foreign'),
        'own' => __('resale.holder.own'),
    ];
    $customerValue = (string) old('customer_id', $customerDefault);
@endphp
    <div class="space-y-3" x-data="resaleHolderPicker"
         data-map="{{ json_encode($foreignByCustomer, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) }}"
         data-holder="{{ $mode }}"
         data-customer="{{ $customerValue }}"
         data-foreign="{{ old('foreign_customer_id', $foreignDefault) }}">
        <x-select-field name="mode" :label="$modeLabel" :hint="$modeHint" x-model="holder">
            @foreach ($modes as $option)
                <option value="{{ $option }}" @selected($mode === $option)>{{ $modeLabels[$option] }}</option>
            @endforeach
        </x-select-field>
        @foreach ($modeHints as $option => $hint)
            <p class="text-xs text-muted" x-show="holder === '{{ $option }}'">{{ $hint }}</p>
        @endforeach
        <div x-show="needsCustomer()">
            <x-select-field name="customer_id" :label="__('resale.inbox.customer')" x-model="customer" :required="! in_array('own', $modes, true)">
                <option value="">—</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->sqid }}" @selected($customerValue === $customer->sqid)>{{ $customer->name }}</option>
                @endforeach
            </x-select-field>
        </div>
        <div x-show="showForeign()">
            <x-select-field name="foreign_customer_id" :label="__('resale.inbox.foreign')" x-model="foreign">
                <option value="">—</option>
                <template x-for="fc in options()" :key="fc.sqid">
                    <option :value="fc.sqid" x-text="fc.name"></option>
                </template>
            </x-select-field>
        </div>
        <p class="text-xs text-warning" x-show="noForeign()">{{ __('resale.dialog.no_foreign_customers') }}</p>
    </div>
