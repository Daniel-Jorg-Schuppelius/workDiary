{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _appointment_inputs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Termin als Datum mit Kalender und Stunde; ohne Datum kein Termin. Erwartet: $order (darf null sein); optional $hidden --}}
@php
    $appointment = $order?->appointmentAt();
    $appointmentDate = (string) old('termin_date', $appointment?->format('Y-m-d') ?? '');
    $appointmentHour = (int) old('termin_hour', $appointment !== null ? (int) $appointment->format('G') : 8);
@endphp
@if ($hidden ?? false)
    <input type="hidden" name="termin_date" value="{{ $appointmentDate }}">
    <input type="hidden" name="termin_hour" value="{{ $appointmentHour }}">
@else
    <span class="kp-status" role="group" aria-label="{{ __('legacy_support.classic.label.appointment_input') }}">
        <input type="date" name="termin_date" value="{{ $appointmentDate }}" aria-label="{{ __('legacy_support.classic.appointment_part.date') }}">
        <select name="termin_hour" aria-label="{{ __('legacy_support.classic.appointment_part.hour') }}">
            @foreach (range(0, 23) as $hourOption)
                <option value="{{ $hourOption }}" @selected($hourOption === $appointmentHour)>{{ __('legacy_support.classic.appointment_part.hour_option', ['hour' => $hourOption]) }}</option>
            @endforeach
        </select>
    </span>
@endif
