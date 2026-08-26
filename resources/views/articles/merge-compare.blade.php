{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : merge-compare.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Artikel zusammenführen'))
@section('nav-title', __('Artikel zusammenführen'))

@php
    /** @var \App\Models\Article $source */
    /** @var \App\Models\Article $target */

    // Reine Anzeigefelder (Identität) — nicht übersteuerbar.
    $identityFields = [
        'name' => __('Name'),
        'number' => __('Artikelnummer'),
        'gtin' => __('GTIN/EAN'),
        'base_unit' => __('Basiseinheit'),
    ];
    // Übersteuerbare Felder — müssen mit ArticleMergeService::FILLABLE_FROM_SOURCE
    // übereinstimmen, sonst ignoriert der Service die Auswahl.
    $overridableFields = [
        'description' => __('Beschreibung'),
        'category' => __('Kategorie'),
        'subcategory' => __('Unterkategorie'),
        'assembly_minutes' => __('Montagezeit (Min.)'),
        'copper_weight' => __('Kupfergewicht'),
        'copper_base_price' => __('Kupfer-Basispreis'),
        'valuation_method' => __('Bewertungsverfahren'),
        'serial_scheme' => __('Seriennummern-Schema'),
    ];
@endphp

@section('content')
<x-index-page :subtitle="__('Wähle pro Feld, ob der Wert des zu löschenden Artikels den Ziel-Wert ersetzen soll. Nicht angehakte, leere Ziel-Felder werden ohnehin aus der Quelle aufgefüllt; befüllte Ziel-Felder bleiben unangetastet.')">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('articles.duplicates.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
    </x-slot:actions>

    <form method="POST" action="{{ route('articles.duplicates.merge') }}"
          data-confirm-dialog
          data-confirm-message="{{ __('„:source“ endgültig in „:target“ zusammenführen? Der Quell-Artikel wird gelöscht.', ['source' => $source->name, 'target' => $target->name]) }}"
          data-confirm-icon="merge" data-confirm-tone="primary" data-confirm-label="{{ __('Zusammenführen') }}">
        @csrf
        <input type="hidden" name="source" value="{{ $source->sqid }}">
        <input type="hidden" name="target" value="{{ $target->sqid }}">

        <x-table>
            <x-slot:head>
                    <tr>
                        <th class="w-44">{{ __('Feld') }}</th>
                        <th>
                            <span class="badge badge-sm badge-success">{{ __('Bleibt') }}</span>
                            <a href="{{ route('articles.show', $target) }}" class="link ml-1">{{ $target->name }}</a>
                        </th>
                        <th>
                            <span class="badge badge-sm badge-ghost">{{ __('Wird gelöscht') }}</span>
                            <a href="{{ route('articles.show', $source) }}" class="link ml-1">{{ $source->name }}</a>
                        </th>
                        <th class="w-40 text-center">{{ __('Wert aus Quelle übernehmen') }}</th>
                    </tr>
            </x-slot:head>
                    @foreach ($identityFields as $field => $label)
                        @php
                            $tv = (string) ($target->getAttribute($field) ?? '');
                            $sv = (string) ($source->getAttribute($field) ?? '');
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $label }}</td>
                            <td>{{ $tv !== '' ? $tv : '—' }}</td>
                            <td class="{{ $tv !== $sv ? 'text-warning' : 'text-muted' }}">{{ $sv !== '' ? $sv : '—' }}</td>
                            <td class="text-center text-muted">—</td>
                        </tr>
                    @endforeach

                    @foreach ($overridableFields as $field => $label)
                        @php
                            $tv = (string) ($target->getAttribute($field) ?? '');
                            $sv = (string) ($source->getAttribute($field) ?? '');
                        @endphp
                        @if ($tv !== '' || $sv !== '')
                            <tr>
                                <td class="text-muted">{{ $label }}</td>
                                <td class="{{ $tv === '' ? 'text-muted' : '' }}">{{ $tv !== '' ? $tv : '—' }}</td>
                                <td class="{{ $tv !== $sv ? 'text-warning' : 'text-muted' }}">{{ $sv !== '' ? $sv : '—' }}</td>
                                <td class="text-center">
                                    @if ($sv !== '' && $tv !== $sv)
                                        <input type="checkbox" class="checkbox checkbox-sm"
                                               name="prefer_source[]" value="{{ $field }}">
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
        </x-table>

        <div class="mt-4 flex flex-wrap justify-end gap-2">
            <a href="{{ route('articles.duplicates.compare', ['target' => $source->sqid, 'source' => $target->sqid]) }}"
               class="btn btn-sm btn-outline">{{ __('Richtung tauschen') }}</a>
            <button class="btn btn-sm btn-primary">{{ __('Zusammenführen →') }}</button>
        </div>
    </form>
</x-index-page>
@endsection
