{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

{{-- Bestellseite (Feature 065, MVP-154): 032-Formular aus der Vorlage des
     Katalogeintrags; Upload-/Signatur-Felder werden im Portal ausgelassen. --}}

@section('content')
    <x-page-toolbar :title="$item->name" :subtitle="$item->description" :back="route('customer.catalog.index')" :back-label="__('Servicekatalog')" class="mb-4" />

    <x-card as="form" class="space-y-3" method="POST" action="{{ route('customer.catalog.order', $item) }}">
        @csrf

        @forelse ($fields as $field)
            <x-field-input :field="$field" :wide="false" />
        @empty
            <p class="text-sm text-muted">{{ __('Für diese Leistung sind keine weiteren Angaben nötig.') }}</p>
        @endforelse

        <x-button type="submit" size="md">{{ __('Bestellung absenden') }}</x-button>
    </x-card>

    @if ($canRequest ?? false)
        <x-card class="mt-4">
            <p class="text-sm">{{ __('customer_intake.portal.catalog_request_hint') }}</p>
            <x-button class="mt-2" :href="route('customer.catalog.request', $item)" tone="outline" icon="request_quote">{{ __('customer_intake.portal.catalog_request') }}</x-button>
        </x-card>
    @endif
@endsection
