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

        <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
            <div>
                <dt class="text-xs text-base-content/60">{{ __('Artikelnummer') }}</dt>
                <dd class="tabular-nums">{{ $article->article_number ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-base-content/60">{{ __('GTIN / Barcode') }}</dt>
                <dd class="tabular-nums">{{ $article->gtin ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-base-content/60">{{ __('Einheit') }}</dt>
                <dd>{{ $article->unit_name ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-base-content/60">{{ __('Führende Preisangabe') }}</dt>
                <dd>
                    @if ($article->leading_price === 'GROSS')
                        {{ __('Brutto') }}
                    @elseif ($article->leading_price === 'NET')
                        {{ __('Netto') }}
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-base-content/60">{{ __('Netto-Preis') }}</dt>
                <dd class="tabular-nums">
                    @if ($article->net_unit_price !== null)
                        {{ number_format((float) $article->net_unit_price, 2, ',', '.') }} {{ $article->currency->value }}
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-base-content/60">{{ __('Brutto-Preis') }}</dt>
                <dd class="tabular-nums">
                    @if ($article->gross_unit_price !== null)
                        {{ number_format((float) $article->gross_unit_price, 2, ',', '.') }} {{ $article->currency->value }}
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-base-content/60">{{ __('Umsatzsteuersatz') }}</dt>
                <dd class="tabular-nums">
                    @if ($article->vat_rate !== null)
                        {{ number_format((float) $article->vat_rate, 0, ',', '.') }} %
                    @else
                        —
                    @endif
                </dd>
            </div>
            @if ($article->description)
                <div class="sm:col-span-2">
                    <dt class="text-xs text-base-content/60">{{ __('Beschreibung') }}</dt>
                    <dd class="whitespace-pre-wrap">{{ $article->description }}</dd>
                </div>
            @endif
            @if ($article->note)
                <div class="sm:col-span-2">
                    <dt class="text-xs text-base-content/60">{{ __('Notiz') }}</dt>
                    <dd class="whitespace-pre-wrap">{{ $article->note }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-xs text-base-content/60">{{ __('Lexoffice-ID') }}</dt>
                <dd class="font-mono text-xs">{{ $article->external_id }}</dd>
            </div>
            <div>
                <dt class="text-xs text-base-content/60">{{ __('Zuletzt synchronisiert') }}</dt>
                <dd>{{ optional($article->synced_at)->format('d.m.Y H:i') ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <x-slot:actions>
        <button type="button" class="btn btn-ghost gap-2" data-entry-modal-close>
            <x-icon name="close" /> {{ __('Schließen') }}
        </button>
    </x-slot:actions>
</x-modal>
