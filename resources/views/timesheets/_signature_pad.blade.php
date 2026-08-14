{{--
  Created on   : Thu May 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _signature_pad.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Signatur-Pad — kompakte Karte unterhalb des Stundenzettels.
    Erwartet: $action (POST-Ziel), $timesheet (Eloquent-Modell).

    Layout: max-w-md Container, Canvas mit fester CSS-Höhe (h-32 = 128px).
    Bugfix: SignaturePad wird VOR dem ersten resize() instanziiert, damit pad.clear()
    nicht ins Leere läuft. requestAnimationFrame stellt sicher, dass offsetWidth > 0
    ist (Layout-Pass abgeschlossen). resize-Listener wird beim Alpine-destroy entfernt.
--}}
{{-- signature_pad ist aus dem globalen app.js-Bundle ausgelagert (eigenes
     Lazy-Entry). Auf layoutbasierten Seiten (z. B. timesheets/show) hier über
     den scripts-Stack nachladen; die öffentliche Signatur-Seite bindet
     signature.js direkt im @vite-Aufruf ein. @once verhindert Doppel-Push. --}}
@once
    @push('scripts')
        @vite('resources/js/signature.js')
    @endpush
@endonce
<div x-data="signaturePad"
     data-name="{{ $timesheet->customer_name }}"
     data-role="{{ $timesheet->customer_role }}"
     data-email="{{ $timesheet->customer_email }}"
     class="flex w-full flex-col gap-3">
    <div class="grid grid-cols-1 gap-2">
        <input type="text" x-model="customerName" placeholder="{{ __('Name') }}"
               class="input input-bordered input-sm w-full" required value="{{ $timesheet->customer_name }}">
        <input type="text" x-model="customerRole" placeholder="{{ __('Rolle / Funktion') }}"
               class="input input-bordered input-sm w-full" value="{{ $timesheet->customer_role }}">
        <input type="email" x-model="customerEmail" placeholder="{{ __('E-Mail (optional)') }}"
               class="input input-bordered input-sm w-full" value="{{ $timesheet->customer_email }}">
    </div>

    <div class="rounded-box border border-base-300 bg-white p-2">
        <canvas x-ref="canvas" class="block h-32 w-full rounded bg-white touch-none"></canvas>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <button type="button" class="btn btn-ghost btn-xs" @click="clear()">{{ __('Leeren') }}</button>
        <span class="text-xs text-base-content/60" x-show="hasSignature">{{ __('Mit dem Klick auf "Signieren" bestätigen Sie die Richtigkeit.') }}</span>
    </div>

    <form method="POST" action="{{ $action }}" @submit="prepare($event)" class="flex">
        @csrf
        <input type="hidden" name="signature"      x-ref="sigInput">
        <input type="hidden" name="customer_name"  :value="customerName">
        <input type="hidden" name="customer_role"  :value="customerRole">
        <input type="hidden" name="customer_email" :value="customerEmail">
        <button class="btn btn-primary btn-sm w-full" :disabled="submitDisabled">{{ __('Signieren') }}</button>
    </form>
</div>
