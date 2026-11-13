{{--
  Created on   : Thu Sep 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : field-display.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anzeige-/Druckwert eines Feldes (MVP-866): Text aus FieldValues::display(),
  Foto und Unterschrift zusätzlich als eingebettetes Bild aus dem Attachment
  (meta_type field:<key>) — Show und PDF teilen dieses Markup.
  Papierlayout (Feature 203, MVP-1651, `paper`): Auswahl, Mehrfachauswahl und
  Checkbox zeigen jede Möglichkeit als ☐/☑ (FieldValues::ballot); eine
  Ergänzung zur Antwort steht darunter.
--}}
@props([
    'field',                 // \App\Services\Fields\FieldDefinition
    'values',                // \App\Services\Fields\FieldValues
    'attachment' => null,    // Attachment mit Bildinhalt (Foto/Unterschrift), optional
    'paper' => false,        // Auswahl als ☐/☑ (Druck, PDF)
])

@php
    $ballot = $paper ? $values->ballot($field) : null;
    $text = $ballot === null ? $values->display($field, app(\App\Services\Fields\FieldExtensionRegistry::class)) : '';
    $note = $values->note($field->key);
    $image = $attachment !== null && \Illuminate\Support\Str::startsWith((string) $attachment->mime, 'image/') ? $attachment : null;
@endphp
@if ($ballot !== null)
    <span {{ $attributes->class('wd-ballot') }}>@foreach ($ballot as $mark)<span class="wd-ballot__mark">{{ $mark['checked'] ? '☑' : '☐' }} {{ $mark['label'] }}</span> @endforeach</span>
@else
    <span {{ $attributes }}>{{ $text }}</span>
@endif
@if ($note !== null)
    <br><span class="wd-field-note-display">{{ __('fields.note.label') }}: {{ $note }}</span>
@endif
@if ($image !== null)
    <br><img src="data:{{ $image->mime }};base64,{{ base64_encode((string) \Illuminate\Support\Facades\Storage::disk($image->disk)->get($image->path)) }}" style="max-height: 120px; max-width: 100%;" alt="{{ $field->label }}">
@endif
