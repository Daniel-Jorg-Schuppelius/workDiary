{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lexoffice-Verknüpfung der Lieferantenakte (Slot `supplier-show.panels`,
  MVP-1039). Erwartet: $supplier, $contactRef (?ExternalReference),
  $categoryRef (?ExternalReference), $categories.
--}}
<x-card class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
            <x-icon name="sync" class="text-muted" /> {{ __('Lexoffice') }}
        </h2>
        @if ($contactRef)
            <x-status-badge tone="success">{{ __('Kontakt verknüpft') }} · {{ Str::limit($contactRef->external_id, 8, '…') }}</x-status-badge>
        @else
            <x-status-badge tone="ghost">{{ __('Noch nicht verknüpft') }}</x-status-badge>
        @endif
    </div>
    @if ($categories->isNotEmpty())
        @can('update', $supplier)
            <div class="grid gap-3 md:grid-cols-2">
                @include('lexoffice::_posting_category', ['action' => route('suppliers.lexoffice.posting-category', $supplier)])
            </div>
        @endcan
    @endif
</x-card>
