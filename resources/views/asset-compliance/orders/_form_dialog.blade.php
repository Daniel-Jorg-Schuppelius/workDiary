{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Prüfauftrag anlegen (MVP-938). Erwartet: $suppliers, $schedules --}}
<x-modal
    :title="__('inspection_order.create')"
    :eyebrow="__('Prüfmittel')"
    icon="handshake"
    tone="primary"
    :action="route('asset-compliance.orders.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('inspection_order.send')"
>
    <x-form-group :legend="__('inspection_order.title')" icon="handshake" tone="primary" cols="2">
        <x-input-field name="title" :label="__('inspection_order.field.title')" :value="old('title')" required span="2" />
        <x-select-field name="supplier_id" :label="__('inspection_order.field.supplier')" required>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->sqid }}" @selected(old('supplier_id') === $supplier->sqid)>{{ $supplier->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="recipient_email" type="email" :label="__('inspection_order.field.recipient_email')" :value="old('recipient_email')" required />
    </x-form-group>
    <x-form-group :legend="__('inspection_order.field.items')" icon="checklist" tone="primary">
        @error('schedule_ids')<p class="text-sm text-error">{{ $message }}</p>@enderror
        @forelse ($schedules as $schedule)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" class="checkbox checkbox-sm" name="schedule_ids[]" value="{{ $schedule->sqid }}" @checked(in_array($schedule->sqid, (array) old('schedule_ids', []), true))>
                <span>{{ $schedule->asset?->name }} <span class="text-muted">({{ $schedule->asset?->asset_no }})</span> — {{ __('inspection_order.due', ['date' => $schedule->due_on->fdate()]) }}</span>
            </label>
        @empty
            <p class="text-sm text-muted">{{ __('inspection_order.no_schedules') }}</p>
        @endforelse
    </x-form-group>
</x-modal>
