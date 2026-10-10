{{--
  Created on   : Sat Oct 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _assign_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Rechnungseingang zuordnen (Feature 163, MVP-1110). --}}
@php
    $isOutgoing = old('direction', $incoming->direction->value) === \App\Enums\Billing\DocumentDirection::Outgoing->value;
    $mode = old('mode', 'existing');
    $current = $incoming->counterparty();
    $currentValue = $current === null ? null : ($current instanceof \App\Models\Supplier\Supplier ? 'supplier:' : 'customer:') . $current->sqid;
    $suggested = collect((array) ($suggestions[$isOutgoing ? 'customers' : 'suppliers'] ?? []))->pluck('id')->all();
@endphp
<x-modal
    :title="__('Eingang zuordnen')"
    :eyebrow="$incoming->invoice_number ?? $incoming->seller_name ?? $incoming->sender_email"
    icon="rule"
    :action="route('finance.incoming-invoices.assign', $incoming)"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Zuordnen')"
>
    <x-form-group :legend="__('Richtung')" icon="swap_horiz" cols="1">
        <x-select-field name="direction" :label="__('Richtung')">
            @foreach ([\App\Enums\Billing\DocumentDirection::Incoming, \App\Enums\Billing\DocumentDirection::Outgoing] as $d)
                <option value="{{ $d->value }}" @selected(old('direction', $incoming->direction->value) === $d->value)>{{ $d->label() }}</option>
            @endforeach
        </x-select-field>
        <p class="text-xs text-muted">{{ __('Eingang: wir sind Käufer, Gegenpartei ist ein Lieferant. Ausgang: wir sind Verkäufer, Gegenpartei ist ein Kunde.') }}</p>
    </x-form-group>

    <x-form-group :legend="__('Zuordnung')" icon="rule" cols="1">
        <div class="fieldset">
            <span class="fieldset-label">{{ __('Weg') }}</span>
            <div class="join flex-wrap">
                <input type="radio" name="mode" value="existing" class="join-item btn btn-sm" aria-label="{{ __('Bestehende Partei') }}" @checked($mode === 'existing')>
                <input type="radio" name="mode" value="collective" class="join-item btn btn-sm" aria-label="{{ __('Sammelkontakt') }}" @checked($mode === 'collective') @disabled(! $collectiveAllowed)>
                <input type="radio" name="mode" value="new" class="join-item btn btn-sm" aria-label="{{ __('Neu aus Belegdaten') }}" @checked($mode === 'new')>
                <input type="radio" name="mode" value="not_invoice" class="join-item btn btn-sm" aria-label="{{ __('Keine Rechnung') }}" @checked($mode === 'not_invoice')>
            </div>
            @unless ($collectiveAllowed)
                <p class="mt-1 text-xs text-warning">{{ __('Reverse Charge, innergemeinschaftlicher Fall oder Drittland: Dafür braucht es einen echten Firmenkontakt, keinen Sammelkontakt.') }}</p>
            @endunless
        </div>

        <x-select-field name="party" :label="__('Bestehende Partei')" :hint="__('Vorschläge stehen oben.')">
            <option value="">—</option>
            @foreach ([['suppliers', 'supplier', __('Lieferanten')], ['customers', 'customer', __('Kunden')]] as [$list, $kind, $groupLabel])
                @php($parties = $kind === 'supplier' ? $suppliers : $customers)
                @php($ranked = $parties->sortByDesc(fn ($p) => in_array($p->id, $kind === ($isOutgoing ? 'customer' : 'supplier') ? $suggested : [], true))->values())
                <optgroup label="{{ $groupLabel }}">
                    @foreach ($ranked as $party)
                        @php($value = $kind . ':' . $party->sqid)
                        <option value="{{ $value }}" @selected(old('party', $currentValue) === $value)>
                            {{ $party->name }}@if ($party->vat_id) · {{ $party->vat_id }}@endif
                            @if (in_array($party->id, $kind === ($isOutgoing ? 'customer' : 'supplier') ? $suggested : [], true)) ({{ __('Vorschlag') }})@endif
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </x-select-field>

        @if ($incoming->sender_email)
            <x-checkbox-field name="remember_sender" :label="__('Absender :email künftig dieser Partei zuordnen', ['email' => $incoming->sender_email])" :checked="(bool) old('remember_sender')" />
        @endif
    </x-form-group>

    <x-form-group :legend="__('Neu aus Belegdaten')" icon="person_add" cols="2" :description="__('Nur beim Weg „Neu aus Belegdaten“.')">
        <x-input-field name="new_name" :label="__('Name')" :value="old('new_name', $prefill['name'])" span="2" />
        <x-input-field name="new_vat_id" :label="__('USt-IdNr.')" :value="old('new_vat_id', $prefill['vat_id'])" />
        <x-input-field name="new_email" type="email" :label="__('E-Mail')" :value="old('new_email', $prefill['email'])" />
        <x-input-field name="new_street" :label="__('Straße')" :value="old('new_street', $prefill['street'])" span="2" />
        <x-input-field name="new_zip" :label="__('PLZ')" :value="old('new_zip', $prefill['zip'])" />
        <x-input-field name="new_city" :label="__('Ort')" :value="old('new_city', $prefill['city'])" />
        <x-input-field name="new_country" :label="__('Land (ISO)')" :value="old('new_country', $prefill['country'])" />
        <x-input-field name="new_iban" :label="__('IBAN')" :value="old('new_iban', $prefill['iban'])" />
    </x-form-group>

    <x-form-group :legend="__('Keine Rechnung')" icon="block" cols="1" :description="__('Nur beim Weg „Keine Rechnung“: Der Eingang wird mit Begründung abgelehnt.')">
        <x-textarea-field name="note" :label="__('Begründung')" :value="old('note')" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
