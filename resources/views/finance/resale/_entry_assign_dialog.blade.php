{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _entry_assign_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Einkauf ohne Abo (Feature 185, MVP-1420) einem Abo des Anbieters zuordnen;
  die Periode ergibt sich aus dem Belegdatum.
--}}
<x-modal
    :title="__('resale.purchase.unmatched.title')"
    icon="link"
    tone="primary"
    size="md"
    :action="route('finance.resale.purchases.assign.store', $entry->sqid)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.purchase.unmatched.assign')"
>
    <div class="text-sm">
        <span class="font-mono">{{ $entry->document_number ?? '—' }}</span> · {{ $entry->entry_date->fdate() }} · {{ $entry->net_amount->format() }}
        <span class="block text-xs text-muted">{{ $entry->description }}</span>
    </div>
    <div class="text-sm text-base-content/70">{{ __('resale.purchase.unmatched.hint') }}</div>
    <x-select-field name="subscription_id" :label="__('resale.field.label')" required>
        <option value="">—</option>
        @foreach ($subscriptions as $subscription)
            <option value="{{ $subscription->sqid }}" @selected(old('subscription_id') === $subscription->sqid)>{{ $subscription->label }} · {{ $subscription->holderLabel() }}@if ($subscription->external_id) · {{ $subscription->external_id }}@endif</option>
        @endforeach
    </x-select-field>
</x-modal>
