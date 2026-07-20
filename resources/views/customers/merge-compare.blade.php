@extends('layouts.app')
@section('title', __('Kunden zusammenführen'))
@section('nav-title', __('Kunden zusammenführen'))

@php
    /** @var \App\Models\Customer $source */
    /** @var \App\Models\Customer $target */

    // Reine Anzeigefelder (Identität) — nicht übersteuerbar.
    $identityFields = [
        'name' => __('Name'),
        'number' => __('Kundennr.'),
    ];
    // Übersteuerbare Felder — müssen mit CustomerMergeService::FILLABLE_FROM_SOURCE
    // übereinstimmen, sonst ignoriert der Service die Auswahl.
    $overridableFields = [
        'company' => __('Firma'),
        'lexoffice_contact_number' => __('Lexoffice-Nr.'),
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
        'hourly_rate' => __('Stundensatz'),
        'internal_rate' => __('Interner Satz'),
        'comment' => __('Notiz'),
        'invoice_text' => __('Rechnungstext'),
        'bank_iban' => __('IBAN'),
        'debtor_no' => __('Debitor-Nr.'),
    ];
@endphp

@section('content')
<x-index-page :subtitle="__('Wähle pro Feld, ob der Wert des zu löschenden Kunden den Ziel-Wert ersetzen soll. Nicht angehakte, leere Ziel-Felder werden ohnehin aus der Quelle aufgefüllt; befüllte Ziel-Felder bleiben unangetastet.')">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('customers.duplicates.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
    </x-slot:actions>

    <form method="POST" action="{{ route('customers.duplicates.merge') }}"
          data-confirm-dialog
          data-confirm-message="{{ __('„:source“ endgültig in „:target“ zusammenführen? Der Quell-Kunde wird gelöscht.', ['source' => $source->name, 'target' => $target->name]) }}"
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
                            <a href="{{ route('customers.show', $target) }}" class="link ml-1">{{ $target->name }}</a>
                        </th>
                        <th>
                            <span class="badge badge-sm badge-ghost">{{ __('Wird gelöscht') }}</span>
                            <a href="{{ route('customers.show', $source) }}" class="link ml-1">{{ $source->name }}</a>
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
            <a href="{{ route('customers.duplicates.compare', ['target' => $source->sqid, 'source' => $target->sqid]) }}"
               class="btn btn-sm btn-outline">{{ __('Richtung tauschen') }}</a>
            <button class="btn btn-sm btn-primary">{{ __('Zusammenführen →') }}</button>
        </div>
    </form>
</x-index-page>
@endsection
