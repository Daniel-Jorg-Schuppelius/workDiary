{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : oauth-error.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Nicht zurückleitbarer OAuth-Fehler (MVP-1065), etwa unbekannter Client oder fremde Rücksprungadresse. Erwartet: $message --}}
@extends('layouts.app')

@section('title', __('mcp.oauth.title'))
@section('nav-title', __('mcp.oauth.title'))

@section('content')
<x-page-shell gap="4">
    <div class="mx-auto w-full max-w-xl">
        <x-card :title="__('mcp.oauth.error.title')" icon="error">
            <p>{{ $message }}</p>
        </x-card>
    </div>
</x-page-shell>
@endsection
