{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _order_hidden.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Unveränderte Felder eines Auftrags für die Teilmasken (Termin, Kundenauswahl). Erwartet: $order; optional $except --}}
@php
    $except = $except ?? [];
    $hiddenFields = [
        'kunde' => \App\Support\Sqid::encode(\App\Legacy\Models\LegacySupportCustomer::class, $order->kunde),
        'kname' => $order->kname,
        'telefon' => $order->telefon,
        'objekt' => $order->objekt,
        'auftrag' => $order->auftrag,
        'wer' => $order->wer,
        'antwort' => $order->antwort,
        'intern' => $order->intern,
        'dauer' => $order->dauer,
        'status' => (string) $order->status,
    ];
@endphp
@foreach ($hiddenFields as $hiddenName => $hiddenValue)
    @unless (in_array($hiddenName, $except, true))
        <input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}">
    @endunless
@endforeach
@unless (in_array('termin', $except, true))
    @include('legacy.classic.support._appointment_inputs', ['order' => $order, 'hidden' => true])
@endunless
