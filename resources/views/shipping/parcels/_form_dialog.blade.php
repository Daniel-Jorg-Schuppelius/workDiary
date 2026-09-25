{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Packstück einer Auslieferung (MVP-900) mit Seriennummern aus dieser Auslieferung. --}}
<x-modal :title="$parcel ? __('shipping.parcel.edit', ['no' => $parcel->position]) : __('shipping.parcel.add')" :eyebrow="$delivery->name_snapshot" icon="package_2" tone="primary"
         :action="$parcel ? route('manufacturing-orders.deliveries.parcels.update', [$order, $delivery, $parcel]) : route('manufacturing-orders.deliveries.parcels.store', [$order, $delivery])"
         :method="$parcel ? 'PUT' : 'POST'" :form-data="['data-entry-form' => '']" :submit-label="__('Speichern')">
    <x-input-field name="weight_grams" type="number" min="1" max="1000000" step="1" :label="__('shipping.field.weight_grams')" :value="old('weight_grams', $parcel?->weight_grams ?? 1000)" required />
    <div class="grid grid-cols-3 gap-2">
        <x-input-field name="length_cm" type="number" min="1" max="400" step="1" :label="__('shipping.field.length_cm')" :value="old('length_cm', $parcel?->length_cm)" />
        <x-input-field name="width_cm" type="number" min="1" max="400" step="1" :label="__('shipping.field.width_cm')" :value="old('width_cm', $parcel?->width_cm)" />
        <x-input-field name="height_cm" type="number" min="1" max="400" step="1" :label="__('shipping.field.height_cm')" :value="old('height_cm', $parcel?->height_cm)" />
    </div>
    <fieldset class="fieldset">
        <legend class="fieldset-legend">{{ __('shipping.parcel.serials') }}</legend>
        @if ($serials->isEmpty())
            <p class="text-sm text-muted">{{ __('shipping.parcel.no_serials') }}</p>
        @else
            @php $chosen = old('serials', $parcel?->serials->map(fn ($s) => $s->sqid)->all() ?? []); @endphp
            <div class="max-h-64 space-y-1 overflow-y-auto">
                @foreach ($serials as $serial)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="serials[]" value="{{ $serial->sqid }}" class="checkbox checkbox-sm" @checked(in_array($serial->sqid, $chosen, true))>
                        <code>{{ $serial->serial_no }}</code>
                    </label>
                @endforeach
            </div>
        @endif
    </fieldset>
</x-modal>
