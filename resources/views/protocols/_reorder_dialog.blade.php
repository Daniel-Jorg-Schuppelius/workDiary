{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reorder_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Protokollpunkte umsortieren (MVP-1573), nur im Entwurf.
     Sortierlogik: resources/js/protocol-item-order.js. Variablen: $protocol, $items (Anzeigereihenfolge). --}}
<x-modal
    :title="__('protocol.action.reorderItems')"
    :eyebrow="$protocol->title"
    icon="swap_vert"
    tone="primary"
    :action="route('protocols.items.order', $protocol)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    <p class="text-sm text-muted">{{ __('protocol.dialog.reorder_hint') }}</p>
    <ol class="space-y-1" data-protocol-order-list>
        @foreach ($items as $item)
            <li class="flex items-center gap-2 rounded-box border border-base-200 bg-base-100 p-2 {{ $item->parent_item_id ? 'ml-6' : '' }}" data-protocol-order-item>
                <x-icon name="drag_indicator" class="cursor-grab touch-none select-none text-muted" data-protocol-order-handle />
                <input type="hidden" name="order_ids[]" value="{{ $item->sqid }}">
                <span class="min-w-0 flex-1 truncate text-sm {{ $item->parent_item_id ? '' : 'font-medium' }}">{{ $item->label }}</span>
                <x-status-badge size="xs" outline>{{ $item->item_type->label() }}</x-status-badge>
            </li>
        @endforeach
    </ol>
</x-modal>
