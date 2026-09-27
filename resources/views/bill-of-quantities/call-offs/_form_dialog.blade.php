{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Abruf anlegen (MVP-931). Erwartet: $bill, $remaining --}}
<x-modal
    :title="__('gaeb.call_off.create')"
    :eyebrow="$bill->name"
    icon="assignment"
    tone="primary"
    :action="route('bill-of-quantities.call-offs.store', $bill)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('gaeb.call_off.create')"
>
    <x-form-group :legend="__('gaeb.call_off.section.head')" icon="assignment" tone="primary" cols="2">
        <x-input-field name="title" :label="__('gaeb.call_off.field.title')" :value="old('title')" required span="2" />
        <div class="md:col-span-2">
            <x-date-range from-name="ordered_on" to-name="due_on" layout="split" :from-label="__('gaeb.call_off.field.ordered_on')" :to-label="__('gaeb.call_off.field.due_on')" :from="old('ordered_on')" :to="old('due_on')" />
        </div>
        <x-textarea-field name="note" :label="__('gaeb.call_off.field.note')" rows="2" span="2">{{ old('note') }}</x-textarea-field>
    </x-form-group>

    <x-form-group :legend="__('gaeb.call_off.section.items')" icon="list" tone="primary">
        @error('quantities')<p class="text-sm text-error">{{ $message }}</p>@enderror
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('gaeb.columns.reference_no') }}</th>
                    <th>{{ __('gaeb.columns.short_text') }}</th>
                    <th class="text-right">{{ __('gaeb.call_off.field.remaining') }}</th>
                    <th class="text-right">{{ __('gaeb.call_off.field.quantity') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($remaining as $row)
                <tr>
                    <td class="font-mono text-xs whitespace-nowrap">{{ $row['item']->reference_no }}</td>
                    <td class="text-sm">{{ $row['item']->short_text ?: '—' }}</td>
                    <td class="text-right tabular-nums text-sm">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['remaining'], 3, withThousandsSeparator: true) }} {{ $row['item']->unit }}</td>
                    <td class="text-right">
                        <input type="number" step="0.001" min="0" max="{{ $row['remaining'] }}" name="quantities[{{ $row['item']->sqid }}]" value="{{ old('quantities.' . $row['item']->sqid) }}"
                               class="input input-bordered input-xs w-24 text-right" aria-label="{{ __('gaeb.call_off.field.quantity') }} {{ $row['item']->reference_no }}" @disabled($row['remaining'] <= 0.0)>
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-form-group>
</x-modal>
