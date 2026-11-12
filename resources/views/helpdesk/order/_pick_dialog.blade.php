{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _pick_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Auswahl der bestellbaren Leistungen (MVP-1549); die Wahl öffnet das
  Bestellformular des Eintrags. Erwartet: $items.
--}}
<x-modal
    :title="__('helpdesk.order.title')"
    :eyebrow="__('Servicekatalog')"
    icon="storefront"
    tone="primary"
    size="md"
    hide-footer>

    @if ($items->isEmpty())
        <x-empty-state compact icon="storefront" :title="__('Derzeit sind keine Leistungen bestellbar.')" />
    @else
        <ul class="divide-y divide-base-300">
            @foreach ($items as $item)
                <li class="flex items-start gap-3 py-2">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold">{{ $item->name }}</div>
                        @if ($item->description)
                            <p class="text-sm text-muted">{{ $item->description }}</p>
                        @endif
                    </div>
                    <x-icon-btn icon="shopping_cart" tone="primary" size="sm" data-entry-modal-trigger
                                :href="route('servicedesk.order.create', $item)" show-label>{{ __('Bestellen') }}</x-icon-btn>
                </li>
            @endforeach
        </ul>
    @endif
</x-modal>
