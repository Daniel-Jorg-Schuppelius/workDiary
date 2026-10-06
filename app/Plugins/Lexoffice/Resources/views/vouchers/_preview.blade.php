{{--
  Created on   : Mon Jun 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _preview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Dialog-Inhalt (eingebettete Modal-Partial) zur Vorschau eines
    Lexoffice-Belegbilds. Wird per data-entry-modal-trigger nachgeladen.
    Das <iframe> zeigt sowohl PDF- als auch Bild-Belege; der eigentliche
    Binär-Stream kommt von der `lexoffice.vouchers.file`-Route.
--}}
<x-modal
    :title="$voucher->voucher_number ? __('Beleg :number', ['number' => $voucher->voucher_number]) : __('Belegbild')"
    :eyebrow="__('Lexoffice-Beleg')"
    icon="receipt_long"
    size="wide">

    <x-slot:headerActions>
        <x-button :href="route('lexoffice.vouchers.file', $voucher)" tone="ghost" icon="open_in_new" class="gap-2"
                target="_blank" rel="noopener" title="{{ __('In neuem Tab öffnen') }}">
            {{ __('Neuer Tab') }}
        </x-button>
    </x-slot:headerActions>

    <div class="h-[68vh] w-full overflow-hidden rounded-box border border-base-300 bg-base-200">
        <iframe src="{{ route('lexoffice.vouchers.file', $voucher) }}"
                class="h-full w-full"
                title="{{ __('Belegbild') }}"></iframe>
    </div>

    <x-slot:footerExtra>
        <x-button :href="route('lexoffice.vouchers.file', [$voucher, 'download' => 1])" size="md" icon="download" class="gap-2">
            {{ __('Herunterladen') }}
        </x-button>
    </x-slot:footerExtra>

    <x-slot:actions>
        <x-button tone="ghost" size="md" icon="close" class="gap-2" data-entry-modal-close>
            {{ __('Schließen') }}
        </x-button>
    </x-slot:actions>
</x-modal>
