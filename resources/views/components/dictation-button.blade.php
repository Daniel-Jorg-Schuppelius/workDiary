{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : dictation-button.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Diktierknopf (MVP-1060): nimmt im Browser auf und fügt die Transkription
  (gegliedert, wenn die KI verfügbar ist) ins Zielfeld ein. Das Mikrofon gibt
  die interne App frei (AllowMicrophone); ohne Whisper erscheint kein Knopf.
--}}
@props([
    'target',                 // CSS-Selektor des Textfelds
    'context' => 'diary',     // Schlüssel aus Dictation::CONTEXTS
])
@php
    $fields = \App\Models\Media\Dictation::CONTEXTS[$context] ?? [];
@endphp
@if (app(\App\Services\Media\SpeechTranscriber::class)->isAvailable())
    <button type="button" {{ $attributes->merge(['class' => 'btn btn-ghost btn-xs gap-1']) }}
            data-dictation
            data-dictation-state="idle"
            data-dictation-target="{{ $target }}"
            data-dictation-context="{{ $context }}"
            data-dictation-fields="{{ implode(',', $fields) }}"
            data-dictation-store="{{ route('dictations.store') }}"
            data-dictation-show="{{ route('dictations.show', ['dictation' => '__ID__']) }}"
            data-label-idle="{{ __('dictation.action.start') }}"
            data-label-recording="{{ __('dictation.action.stop') }}"
            data-label-working="{{ __('dictation.action.working') }}"
            data-label-denied="{{ __('dictation.error.denied') }}"
            data-label-unsupported="{{ __('dictation.error.unsupported') }}"
            @foreach ($fields as $field) data-heading-{{ str_replace('_', '-', $field) }}="{{ __('dictation.field.' . $field) }}" @endforeach
            aria-label="{{ __('dictation.action.start') }}" title="{{ __('dictation.action.start') }}">
        <x-icon name="mic" />
        <span data-dictation-text>{{ __('dictation.action.start') }}</span>
    </button>
@endif
