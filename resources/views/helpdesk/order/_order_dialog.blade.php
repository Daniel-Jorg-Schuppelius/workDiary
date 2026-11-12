{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _order_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Interne Katalogbestellung (MVP-1549): Formular aus der Vorlage des
  Katalogeintrags. Erwartet: $item, $fields (FieldSchema).
--}}
@php
    /** @var \App\Models\Procurement\RequestItem $item */
    $steps = count((array) ($item->approval_chain ?? []));
@endphp

<x-modal
    :title="$item->name"
    :eyebrow="__('Servicekatalog')"
    icon="shopping_cart"
    tone="primary"
    size="md"
    :action="route('servicedesk.order.store', $item)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Bestellung absenden')">

    @if ($item->description)
        <p class="text-sm text-muted mb-3">{{ $item->description }}</p>
    @endif

    <div class="space-y-3">
        @forelse ($fields as $field)
            <x-field-input :field="$field" :wide="false" />
        @empty
            <p class="text-sm text-muted">{{ __('Für diese Leistung sind keine weiteren Angaben nötig.') }}</p>
        @endforelse
    </div>

    <p class="mt-3 text-xs text-muted">
        {{ $steps === 0 ? __('helpdesk.order.noApproval') : trans_choice('helpdesk.order.approvalSteps', $steps, ['count' => $steps]) }}
    </p>
</x-modal>
