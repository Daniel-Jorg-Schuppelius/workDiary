{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _appointment_inputs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Termin in vier Feldern wie im Altportal (jjjj-mm-tt hh). Erwartet: $order (darf null sein); optional $hidden --}}
@php
    $appointment = $order?->appointmentAt();
    $appointmentParts = [
        'tj' => old('tj', $appointment?->format('Y')),
        'tm' => old('tm', $appointment?->format('m')),
        'tt' => old('tt', $appointment?->format('d')),
        'th' => old('th', $appointment?->format('H')),
    ];
@endphp
@if ($hidden ?? false)
    @foreach ($appointmentParts as $partName => $partValue)
        <input type="hidden" name="{{ $partName }}" value="{{ $partValue }}">
    @endforeach
@else
    <span role="group" aria-label="{{ __('legacy_support.classic.label.appointment_input') }}">
        <input type="text" name="tj" value="{{ $appointmentParts['tj'] }}" maxlength="4" inputmode="numeric" class="kp-year" aria-label="{{ __('legacy_support.classic.appointment_part.year') }}">
        <input type="text" name="tm" value="{{ $appointmentParts['tm'] }}" maxlength="2" inputmode="numeric" class="kp-short" aria-label="{{ __('legacy_support.classic.appointment_part.month') }}">
        <input type="text" name="tt" value="{{ $appointmentParts['tt'] }}" maxlength="2" inputmode="numeric" class="kp-short" aria-label="{{ __('legacy_support.classic.appointment_part.day') }}">
        <input type="text" name="th" value="{{ $appointmentParts['th'] }}" maxlength="2" inputmode="numeric" class="kp-short" aria-label="{{ __('legacy_support.classic.appointment_part.hour') }}">
    </span>
@endif
