{{--
  Created on   : Mon Jun 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _details.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Dialog-Inhalt (eingebettete Modal-Partial) mit den Stammdaten eines
    Lexoffice-Artikels (Produkt/Leistung). Wird per data-entry-modal-trigger
    nachgeladen. Rein lesend — Pflege erfolgt in Lexoffice.
--}}
<x-modal
    :title="$article->name"
    :eyebrow="__('Produkt / Leistung')"
    icon="inventory_2"
    size="wide">

    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            <x-status-badge :tone="$article->type === 'SERVICE' ? 'info' : 'neutral'" size="xs">
                {{ $article->type === 'SERVICE' ? __('Leistung') : __('Produkt') }}
            </x-status-badge>
            @if ($article->archived_at)
                <x-status-badge tone="ghost" size="xs">{{ __('archiviert') }}</x-status-badge>
            @else
                <x-status-badge tone="success" size="xs">{{ __('aktiv') }}</x-status-badge>
            @endif
        </div>

        <x-detail-grid layout="cells" small-labels>
            <x-detail-grid.row :label="__('Artikelnummer')" class="tabular-nums">{{ $article->article_number ?: '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('GTIN / Barcode')" class="tabular-nums">{{ $article->gtin ?: '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('Einheit')">{{ $article->unit_name ?: '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('Führende Preisangabe')">
                @if ($article->leading_price === 'GROSS')
                    {{ __('Brutto') }}
                @elseif ($article->leading_price === 'NET')
                    {{ __('Netto') }}
                @else
                    —
                @endif
            </x-detail-grid.row>
            <x-detail-grid.row :label="__('Netto-Preis')" class="tabular-nums">
                @if ($article->net_unit_price !== null)
                    {{ $article->net_unit_price?->withScale(2)->format(withSymbol: false) ?? '0,00' }} {{ $article->currency->value }}
                @else
                    —
                @endif
            </x-detail-grid.row>
            <x-detail-grid.row :label="__('Brutto-Preis')" class="tabular-nums">
                @if ($article->gross_unit_price !== null)
                    {{ $article->gross_unit_price?->withScale(2)->format(withSymbol: false) ?? '0,00' }} {{ $article->currency->value }}
                @else
                    —
                @endif
            </x-detail-grid.row>
            <x-detail-grid.row :label="__('Umsatzsteuersatz')" class="tabular-nums">
                @if ($article->vat_rate !== null)
                    {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($article->vat_rate !== null ? (float) $article->vat_rate->getNumericValue() : 0.0, 0, withThousandsSeparator: true) }} %
                @else
                    —
                @endif
            </x-detail-grid.row>
            @if ($article->description)
                <x-detail-grid.row :label="__('Beschreibung')" full class="whitespace-pre-wrap">{{ $article->description }}</x-detail-grid.row>
            @endif
            @if ($article->note)
                <x-detail-grid.row :label="__('Notiz')" full class="whitespace-pre-wrap">{{ $article->note }}</x-detail-grid.row>
            @endif
            <x-detail-grid.row :label="__('Lexoffice-ID')" class="font-mono text-xs">{{ $article->external_id }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('Zuletzt synchronisiert')">{{ $article->synced_at?->fdatetime() ?: '—' }}</x-detail-grid.row>
        </x-detail-grid>
    </div>

    <x-slot:actions>
        <x-button tone="ghost" size="md" icon="close" class="gap-2" data-entry-modal-close>
            {{ __('Schließen') }}
        </x-button>
    </x-slot:actions>
</x-modal>
