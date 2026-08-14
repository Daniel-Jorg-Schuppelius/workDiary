{{--
  Created on   : Thu Jul 30 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : transfer.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('b2b.catalog.layout')

@section('title', __('b2b_catalog.public.transfer_title'))

@section('content')
    <div class="card">
        <p>{{ __('b2b_catalog.public.transfer_hint') }}</p>

        {{-- OCI-4.0-Warenkorb-Rückgabe: selbstabsendendes POST an die HOOK_URL
             des Einkaufssystems. form-action der CSP wird von
             B2bCatalogSecurityHeaders um die HOOK_URL-Origin erweitert. --}}
        <form id="oci-cart" method="POST" action="{{ $hookUrl }}" target="{{ $returnTarget }}">
            @foreach ($fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <button type="submit" class="btn">{{ __('b2b_catalog.public.transfer_submit') }}</button>
        </form>
    </div>

    <script @cspNonce>
        document.getElementById('oci-cart').submit();
    </script>
@endsection
