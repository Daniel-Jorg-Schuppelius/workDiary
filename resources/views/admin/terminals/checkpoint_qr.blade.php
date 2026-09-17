{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : checkpoint_qr.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Aushang eines Check-in-Punkts (MVP-800). Variablen: $checkpoint, $url, $qrDataUri, $backUrl --}}
@extends('layouts.print')

@push('print-styles')
    <style>
        .checkpoint { max-width: 14cm; margin: 1.5cm auto; text-align: center; }
        .checkpoint h1 { font-size: 22pt; margin: 0 0 4pt; }
        .checkpoint .kind { font-size: 11pt; color: #555; margin-bottom: 16pt; }
        .checkpoint img { width: 9cm; height: 9cm; }
        .checkpoint .hint { font-size: 11pt; margin-top: 14pt; }
        .checkpoint .url { font-family: monospace; font-size: 8pt; word-break: break-all; color: #555; margin-top: 10pt; }
        .checkpoint .nfc { font-size: 8pt; color: #555; margin-top: 18pt; }
    </style>
@endpush

@section('content')
    <div class="checkpoint">
        <h1>{{ $checkpoint->name }}</h1>
        <div class="kind">
            {{ $checkpoint->kind->label() }}@if ($checkpoint->vehicle) · {{ $checkpoint->vehicle->displayName() }}@elseif ($checkpoint->site) · {{ $checkpoint->site->name }}@endif
        </div>
        <img src="{{ $qrDataUri }}" alt="{{ __('terminal.checkpoint.qr.alt', ['name' => $checkpoint->name]) }}">
        <div class="hint">{{ __('terminal.checkpoint.qr.hint') }}</div>
        <div class="url">{{ $url }}</div>
        <div class="nfc no-print">{{ __('terminal.checkpoint.qr.nfc_hint') }}</div>
    </div>
@endsection
