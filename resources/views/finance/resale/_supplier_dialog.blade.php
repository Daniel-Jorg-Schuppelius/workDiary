{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _supplier_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lieferant → Anbieter (Feature 185, MVP-1419): Bestand mit Lösen, neue
  Zuordnung mit Namensvorschlag — zugeordnet wird nie automatisch.
--}}
<x-modal
    :title="__('resale.purchase.supplier.title')"
    icon="local_shipping"
    tone="primary"
    size="md"
    :action="route('finance.resale.purchases.suppliers.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.purchase.supplier.add')"
>
    <div class="text-sm text-base-content/70">{{ __('resale.purchase.supplier.hint') }}</div>
    @if ($mappings->isNotEmpty())
        <ul class="text-sm border border-base-300 rounded-box divide-y divide-base-300">
            @foreach ($mappings as $mapping)
                <li class="flex items-center justify-between gap-2 px-3 py-1.5">
                    <span>{{ $mapping->supplier?->name ?? '—' }} <span class="text-muted">→</span> {{ $mapping->provider->label() }}</span>
                    <x-icon-btn icon="link_off" size="xs" tone="ghost" :title="__('resale.purchase.supplier.remove')"
                                form="resale-supplier-unlink-{{ $mapping->sqid }}" type="submit" />
                </li>
            @endforeach
        </ul>
    @else
        <x-empty-state compact icon="local_shipping" :title="__('resale.purchase.supplier.empty')" />
    @endif
    <x-select-field name="supplier_id" :label="__('resale.purchase.supplier.supplier')" required>
        <option value="">—</option>
        @foreach ($candidates as $candidate)
            <option value="{{ $candidate['supplier']->sqid }}" @selected(old('supplier_id') === $candidate['supplier']->sqid)>{{ $candidate['supplier']->name }}@if ($candidate['suggested'] !== null) · {{ __('resale.purchase.supplier.suggested', ['provider' => $candidate['suggested']->label()]) }}@endif</option>
        @endforeach
    </x-select-field>
    <x-select-field name="provider" :label="__('resale.field.provider')" required>
        @foreach ($providers as $provider)
            <option value="{{ $provider->value }}" @selected(old('provider', 'qualityhosting') === $provider->value)>{{ $provider->label() }}</option>
        @endforeach
    </x-select-field>
</x-modal>
{{-- Lösen je Zuordnung: eigene Formulare außerhalb des Dialogformulars (kein Formular im Formular), per form-Attribut verbunden. --}}
@foreach ($mappings as $mapping)
    <form id="resale-supplier-unlink-{{ $mapping->sqid }}" method="POST" action="{{ route('finance.resale.purchases.suppliers.destroy', $mapping->sqid) }}" class="hidden"
          data-confirm-dialog data-confirm-message="{{ __('resale.purchase.supplier.confirm_remove') }}" data-confirm-tone="warning">
        @csrf
        @method('DELETE')
    </form>
@endforeach
