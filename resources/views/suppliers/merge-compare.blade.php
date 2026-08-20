{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : merge-compare.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Lieferanten zusammenführen'))
@section('nav-title', __('Lieferanten zusammenführen'))

@php
    /** @var \App\Models\Supplier $source */
    /** @var \App\Models\Supplier $target */

    // Reine Anzeigefelder (Identität) — nicht übersteuerbar.
    $identityFields = [
        'name' => __('Name'),
        'number' => __('Nummer'),
    ];
    // Übersteuerbare Felder — müssen mit SupplierMergeService::FILLABLE_FROM_SOURCE
    // übereinstimmen, sonst ignoriert der Service die Auswahl.
    $overridableFields = [
        'company' => __('Firma'),
        'vendor_number' => __('Lieferantennr.'),
        'vat_id' => __('USt-IdNr.'),
        'tax_number' => __('Steuernr.'),
        'contact_name' => __('Ansprechpartner'),
        'email' => __('E-Mail'),
        'phone' => __('Telefon'),
        'mobile' => __('Mobil'),
        'homepage' => __('Webseite'),
        'address_street' => __('Straße'),
        'address_zip' => __('PLZ'),
        'address_city' => __('Ort'),
        'country' => __('Land'),
        'comment' => __('Notiz'),
        'bank_account_holder' => __('Kontoinhaber'),
        'bank_iban' => __('IBAN'),
        'bank_bic' => __('BIC'),
    ];
@endphp

@section('content')
<x-index-page :subtitle="__('Wähle pro Feld, ob der Wert des zu löschenden Lieferanten den Ziel-Wert ersetzen soll. Nicht angehakte, leere Ziel-Felder werden ohnehin aus der Quelle aufgefüllt; befüllte Ziel-Felder bleiben unangetastet.')">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('suppliers.duplicates.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
    </x-slot:actions>

    <form method="POST" action="{{ route('suppliers.duplicates.merge') }}"
          data-confirm-dialog
          data-confirm-message="{{ __('„:source“ endgültig in „:target“ zusammenführen? Der Quell-Lieferant wird gelöscht.', ['source' => $source->name, 'target' => $target->name]) }}"
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
                            <a href="{{ route('suppliers.show', $target) }}" class="link ml-1">{{ $target->name }}</a>
                        </th>
                        <th>
                            <span class="badge badge-sm badge-ghost">{{ __('Wird gelöscht') }}</span>
                            <a href="{{ route('suppliers.show', $source) }}" class="link ml-1">{{ $source->name }}</a>
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
                            <td class="text-base-content/60">{{ $label }}</td>
                            <td>{{ $tv !== '' ? $tv : '—' }}</td>
                            <td class="{{ $tv !== $sv ? 'text-warning' : 'text-base-content/50' }}">{{ $sv !== '' ? $sv : '—' }}</td>
                            <td class="text-center text-base-content/30">—</td>
                        </tr>
                    @endforeach

                    @foreach ($overridableFields as $field => $label)
                        @php
                            $tv = (string) ($target->getAttribute($field) ?? '');
                            $sv = (string) ($source->getAttribute($field) ?? '');
                        @endphp
                        @if ($tv !== '' || $sv !== '')
                            <tr>
                                <td class="text-base-content/60">{{ $label }}</td>
                                <td class="{{ $tv === '' ? 'text-base-content/30' : '' }}">{{ $tv !== '' ? $tv : '—' }}</td>
                                <td class="{{ $tv !== $sv ? 'text-warning' : 'text-base-content/50' }}">{{ $sv !== '' ? $sv : '—' }}</td>
                                <td class="text-center">
                                    @if ($sv !== '' && $tv !== $sv)
                                        <input type="checkbox" class="checkbox checkbox-sm"
                                               name="prefer_source[]" value="{{ $field }}">
                                    @else
                                        <span class="text-base-content/30">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
        </x-table>

        <div class="mt-4 flex flex-wrap justify-end gap-2">
            <a href="{{ route('suppliers.duplicates.compare', ['target' => $source->sqid, 'source' => $target->sqid]) }}"
               class="btn btn-sm btn-outline">{{ __('Richtung tauschen') }}</a>
            <button class="btn btn-sm btn-primary">{{ __('Zusammenführen →') }}</button>
        </div>
    </form>
</x-index-page>
@endsection
