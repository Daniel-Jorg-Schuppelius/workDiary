{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _purchase_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Eingangsbeleg einer registrierten Quelle (Lexoffice-Spiegel, lokale Ausgabe,
  Eingangs-E-Rechnung) einem Anbieter zuweisen und pro rata auf die Perioden
  des Monats verteilen (Feature 152, MVP-762; Review 2026-09-11, Einkauf) —
  für Rechnungen ohne Positionsangaben (Telekom-Sammelrechnung mit
  Marketplace-Anteil). Das Suchfeld filtert die Belegliste (Nummer, Lieferant)
  im Dialog; der Optionswert ist der Quellschlüssel des Belegs.
--}}
<x-modal
    :title="__('resale.purchase.allocate_title')"
    icon="receipt"
    tone="primary"
    size="lg"
    :action="route('finance.resale.purchases.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.purchase.allocate_submit')"
>
    <div class="text-sm text-base-content/70">{{ __('resale.purchase.allocate_hint') }}</div>
    <div data-filter-scope>
        <x-select-field name="document" :label="__('resale.purchase_document.field')" required data-filter-list :hint="__('resale.purchase_document.hint')">
            <x-slot:beforeSelect>
                <input type="search" data-filter-search value="{{ $q }}" class="input input-sm input-bordered w-full mb-2"
                       placeholder="{{ __('resale.purchase_dialog.search') }}" aria-label="{{ __('resale.purchase_dialog.search') }}">
            </x-slot:beforeSelect>
            <option value="">—</option>
            @foreach ($documents as $document)
                <option value="{{ $document->key }}" data-haystack="{{ $document->haystack() }}" @selected(old('document') === $document->key)>{{ __('resale.purchase_document.source.' . $document->sourceKey) }} · {{ $document->label() }}{{ isset($allocated[$document->identity()]) ? ' ✓' : '' }}</option>
            @endforeach
        </x-select-field>
        <p data-filter-empty class="text-xs text-muted hidden">{{ __('resale.purchase_dialog.no_match') }}</p>
        <div class="flex flex-wrap items-center gap-1 mt-1 text-xs text-muted">
            <span>{{ __('resale.purchase_document.sources_hint') }}:</span>
            @foreach ($sources as $source)
                <x-status-badge size="xs" tone="neutral" :label="__('resale.purchase_document.source.' . $source->key())" />
            @endforeach
        </div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <x-select-field name="provider" :label="__('resale.field.provider')" required :hint="__('resale.purchase_dialog.domain_provider')">
            @foreach ($providers as $provider)
                <option value="{{ $provider->value }}" @selected(old('provider', 'telekom_marketplace') === $provider->value)>{{ $provider->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="net_amount" type="number" step="0.01" min="0.01" :label="__('resale.purchase.field.share')" :value="old('net_amount')" required :hint="__('resale.purchase.share_hint')" />
        <x-input-field name="month" type="month" :label="__('resale.purchase.field.month')" :value="old('month', \App\Support\Tz::now()->subMonth()->format('Y-m'))" required />
    </div>
</x-modal>
