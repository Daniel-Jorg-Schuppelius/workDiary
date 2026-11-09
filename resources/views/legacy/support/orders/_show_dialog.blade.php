{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _show_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Support-Auftrag lesen (Auftragsliste und Archiv). Erwartet: $order, $archived, $writeEnabled --}}
<x-modal
    :title="__('legacy_support.orders.show_title', ['number' => $order->id])"
    :eyebrow="$archived ? __('legacy_support.archive.eyebrow') : __('legacy_support.orders.eyebrow')"
    :icon="$archived ? 'inventory_2' : 'support'"
    :badge="$order->statusLabel()"
    :badge-tone="$order->statusTone()"
    tone="ghost">
    @include('legacy.support.orders._show_body', ['order' => $order])

    @if (! $archived && $writeEnabled)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('legacy.support.orders.destroy', $order) }}" class="inline"
                  data-confirm-dialog
                  data-confirm-title="{{ __('legacy_support.orders.delete') }}"
                  data-confirm-message="{{ __('legacy_support.orders.delete_confirm', ['number' => $order->id]) }}"
                  data-confirm-label="{{ __('legacy_support.orders.delete') }}">
                @csrf
                @method('DELETE')
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('legacy_support.orders.delete') }}</x-icon-btn>
            </form>
        </x-slot:footerExtra>

        <x-slot:actions>
            <x-icon-btn icon="close" size="sm" type="button" data-entry-modal-close show-label>{{ __('legacy_support.close') }}</x-icon-btn>
            <x-icon-btn icon="edit" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('legacy.support.orders.edit', $order)"
                        show-label>{{ __('legacy_support.orders.edit') }}</x-icon-btn>
        </x-slot:actions>
    @endif
</x-modal>
