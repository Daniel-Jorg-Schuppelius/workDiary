{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _sign_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Fahrt mit Unterschrift abschließen (MVP-992). Variablen: $log (TravelLog)
--}}
@once @push('scripts') @vite('resources/js/signature.js') @endpush @endonce
<x-modal :title="__('Mit Unterschrift abschließen')" icon="gesture" tone="primary"
         :eyebrow="$log->date?->format('d.m.Y') . ' · ' . ($log->vehicleEntity?->license_plate ?? '')">
    <div x-data="signaturePad" class="flex flex-col gap-3">
        <p class="text-sm text-muted">{{ __('Mit der Unterschrift bestätigen Sie die Angaben dieser Fahrt; sie wird damit festgeschrieben.') }}</p>
        <p class="text-sm">{{ $log->odometer_start_km }} → {{ $log->odometer_end_km }} km · {{ $log->trip_kind?->label() }} · {{ $log->purpose }}</p>
        <div class="rounded-box border border-base-300 bg-white p-2">
            <canvas x-ref="canvas" class="block h-32 w-full touch-none rounded bg-white"></canvas>
        </div>
        <button type="button" class="btn btn-ghost btn-xs self-start" @click="clear()">{{ __('Leeren') }}</button>
        <form method="POST" action="{{ route('travel-logs.sign', $log) }}" @submit="prepare($event)" class="flex">
            @csrf
            <input type="hidden" name="signature" x-ref="sigInput">
            <button class="btn btn-primary btn-sm w-full" :disabled="isEmpty">{{ __('Unterschreiben') }}</button>
        </form>
    </div>
    <x-slot:actions>
        <x-button type="button" tone="ghost" size="md" data-entry-modal-close>{{ __('Schließen') }}</x-button>
    </x-slot:actions>
</x-modal>
