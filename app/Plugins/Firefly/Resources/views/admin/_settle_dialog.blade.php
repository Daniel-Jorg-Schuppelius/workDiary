{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _settle_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Bewegung als erledigt markieren (MVP-1628). Erwartet: $transaction --}}
@php
    /** @var \App\Plugins\Firefly\Models\FireflyTransaction $transaction */
@endphp
<x-modal
    :title="__('firefly::firefly.settle.title')"
    :eyebrow="trim(($transaction->counterparty_name ?? $transaction->kind->label()) . ' · ' . $transaction->booked_amount->format(), ' ·')"
    icon="task_alt"
    tone="primary"
    size="md"
    :action="route('admin.firefly.transactions.settle', $transaction)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('firefly::firefly.settle.action')">
    <p class="mb-2 text-sm text-base-content/70">{{ __('firefly::firefly.settle.intro') }}</p>
    <x-textarea-field name="note" :label="__('firefly::firefly.settle.note')" required rows="3" minlength="3" maxlength="500" :value="old('note')" />
</x-modal>
