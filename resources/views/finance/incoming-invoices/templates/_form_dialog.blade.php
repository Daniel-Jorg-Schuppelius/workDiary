{{--
  Created on   : Tue Oct 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Belegvorlage bearbeiten (Phase 155, MVP-1253): Name und Lieferant. Variablen: $template, $suppliers --}}
<x-modal
    :title="__('Vorlage bearbeiten')"
    :eyebrow="__('Belegvorlagen')"
    icon="crop_free"
    tone="primary"
    :action="route('finance.incoming-invoices.templates.update', $template)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    <x-input-field name="name" :label="__('Name der Vorlage')" :value="old('name', $template->name)" required maxlength="191" />
    <x-select-field name="supplier" :label="__('Lieferant')" :hint="__('Der Lieferant der Vorlage wird jedem getroffenen Beleg zugeordnet; sein Name ist der Verkäufer.')">
        <option value="">—</option>
        @foreach ($suppliers as $supplier)
            <option value="{{ $supplier->sqid }}" @selected(old('supplier', $template->supplier?->sqid) === $supplier->sqid)>{{ $supplier->name }}@if ($supplier->vat_id) · {{ $supplier->vat_id }}@endif</option>
        @endforeach
    </x-select-field>
    <p class="text-xs text-muted">{{ __('Zonen ändern Sie im Zonen-Editor eines Belegs dieser Vorlage.') }}</p>
</x-modal>
