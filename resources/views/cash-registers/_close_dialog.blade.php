{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _close_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- MVP-414: Tagesabschluss mit Kassensturz. Variablen: $register, $expected --}}
<x-modal
    :title="__('Tagesabschluss')"
    :eyebrow="$register->name"
    icon="lock"
    tone="warning"
    :action="route('cash-registers.close', $register)"
    method="POST"
    :submit-label="__('Abschließen')">

    <div class="alert alert-warning text-sm">
        <span>{{ __('Nach dem Abschluss sind alle Buchungen bis einschließlich des Abschlussdatums festgeschrieben.') }}</span>
    </div>

    <x-form-group :legend="__('Kassensturz')" icon="lock" tone="warning" cols="2">
        <x-input-field name="closing_date" type="date" :label="__('Abschlussdatum')" required :value="old('closing_date', now()->format('Y-m-d'))" />
        <x-input-field name="counted_balance" type="number" :label="__('Gezählter Bestand (EUR)')" required min="0" step="0.01"
                       :value="old('counted_balance', number_format($expected, 2, '.', ''))"
                       :hint="__('Soll laut Kassenbuch: :expected', ['expected' => number_format($expected, 2, ',', '.')])" />
        <div class="fieldset" style="grid-column: span 2;">
            <label class="fieldset-label" for="cc-note">{{ __('Notiz (bei Differenz empfohlen)') }}</label>
            <textarea id="cc-note" name="note" rows="2" maxlength="500" class="textarea textarea-bordered w-full">{{ old('note') }}</textarea>
        </div>
    </x-form-group>

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
