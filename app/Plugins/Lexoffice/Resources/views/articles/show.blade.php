{{--
  Created on   : Mon Jun 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', $article->name . ' — ' . __('Produkt / Leistung'))
@section('nav-title', __('Produkte & Leistungen'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$article->name">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm"
                            :href="route('lexoffice.articles.index')"
                            show-label>{{ __('Zurück') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ $article->name }}</h1>
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
                <dt class="text-xs text-muted">{{ __('Artikelnummer') }}</dt>
                <dd class="tabular-nums">{{ $article->article_number ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">{{ __('GTIN / Barcode') }}</dt>
                <dd class="tabular-nums">{{ $article->gtin ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">{{ __('Einheit') }}</dt>
                <dd>{{ $article->unit_name ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">{{ __('Führende Preisangabe') }}</dt>
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
                <dt class="text-xs text-muted">{{ __('Netto-Preis') }}</dt>
                <dd class="tabular-nums">
                    @if ($article->net_unit_price !== null)
                        {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($article->net_unit_price?->toFloat() ?? 0.0, 2, withThousandsSeparator: true) }} {{ $article->currency->value }}
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted">{{ __('Brutto-Preis') }}</dt>
                <dd class="tabular-nums">
                    @if ($article->gross_unit_price !== null)
                        {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($article->gross_unit_price?->toFloat() ?? 0.0, 2, withThousandsSeparator: true) }} {{ $article->currency->value }}
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted">{{ __('Umsatzsteuersatz') }}</dt>
                <dd class="tabular-nums">
                    @if ($article->vat_rate !== null)
                        {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($article->vat_rate !== null ? (float) $article->vat_rate->getNumericValue() : 0.0, 0, withThousandsSeparator: true) }} %
                    @else
                        —
                    @endif
                </dd>
            </div>
            @if ($article->description)
                <div class="sm:col-span-2">
                    <dt class="text-xs text-muted">{{ __('Beschreibung') }}</dt>
                    <dd class="whitespace-pre-wrap">{{ $article->description }}</dd>
                </div>
            @endif
            @if ($article->note)
                <div class="sm:col-span-2">
                    <dt class="text-xs text-muted">{{ __('Notiz') }}</dt>
                    <dd class="whitespace-pre-wrap">{{ $article->note }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-xs text-muted">{{ __('Lexoffice-ID') }}</dt>
                <dd class="font-mono text-xs">{{ $article->external_id }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">{{ __('Zuletzt synchronisiert') }}</dt>
                <dd>{{ optional($article->synced_at)->format('d.m.Y H:i') ?: '—' }}</dd>
            </div>
        </dl>
    </x-card>
</x-page-shell>
@endsection
