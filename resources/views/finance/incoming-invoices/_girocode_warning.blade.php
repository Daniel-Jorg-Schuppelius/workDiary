{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _girocode_warning.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- GiroCode-Gegenprobe (Feature 208, MVP-1663): abweichende IBAN warnt im Zuordnungsdialog und auf der Detailseite; die Sperre sitzt im Zahllauf. --}}
@php
    $giroCheck = app(\App\Services\Invoicing\GiroCode\GiroCodeCheck::class);
    $giroBlocking = array_values(array_intersect($giroCheck->findings($incoming), \App\Services\Invoicing\GiroCode\GiroCodeCheck::BLOCKING));
    $giroConfirmed = $giroBlocking !== [] && $giroCheck->isConfirmed($incoming);
@endphp
@if ($giroBlocking !== [])
    <div role="alert" @class(['alert text-sm', 'alert-warning' => ! $giroConfirmed, 'alert-info' => $giroConfirmed])>
        <x-icon name="gpp_maybe" />
        <div>
            @foreach ($giroBlocking as $finding)
                <p>{{ __('incoming_pdf.girocode.finding.' . $finding) }}</p>
            @endforeach
            @if ($giroConfirmed)
                <p class="text-xs">{{ __('incoming_pdf.girocode.confirmed', ['date' => $incoming->girocode_confirmed_at?->orgTz()->isoFormat('L LT')]) }}</p>
            @else
                <p class="text-xs">{{ __('incoming_pdf.girocode.blocking') }}</p>
            @endif
        </div>
    </div>
@endif
