{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _customs_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zollpapiere (MVP-1007): ohne data-entry-form, damit das PDF im neuen Tab öffnet. --}}
<x-modal
    :title="__('shipping.customs.dialog_title')"
    :eyebrow="$delivery->name_snapshot"
    icon="public"
    tone="info"
    :action="$missing === [] ? route('manufacturing-orders.deliveries.customs.pdf', [$order, $delivery]) : null"
    method="POST"
    :form-data="['target' => '_blank']"
    :submit-label="__('shipping.customs.submit')"
>
    <div class="alert {{ $required ? 'alert-warning' : 'alert-info' }} text-sm" role="status"><x-icon name="{{ $required ? 'warning' : 'info' }}" /><span>{{ $required ? __('shipping.customs.required_hint') : __('shipping.customs.eu_hint') }}</span></div>

    @if ($missing !== [])
        <div class="alert alert-error mt-3 text-sm" role="alert"><x-icon name="error" /><span>{{ __('shipping.customs.error.missing', ['article' => $delivery->name_snapshot, 'fields' => implode(', ', $missing)]) }}</span></div>
    @else
        <x-select-field name="export_reason" :label="__('shipping.customs.reason')" required :hint="__('shipping.customs.reason_hint')" class="mt-3">
            @foreach ($reasons as $reason)
                <option value="{{ $reason->value }}" @selected(($delivery->export_reason ?? \App\Enums\Shipping\ShipmentExportReason::Sale) === $reason)>{{ $reason->label() }}</option>
            @endforeach
        </x-select-field>
    @endif
</x-modal>
