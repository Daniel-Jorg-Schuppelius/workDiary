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
--}}
@props([
    'field',                 // \App\Services\Fields\FieldDefinition
    'values',                // \App\Services\Fields\FieldValues
    'attachment' => null,    // Attachment mit Bildinhalt (Foto/Unterschrift), optional
])

@php
    $text = $values->display($field, app(\App\Services\Fields\FieldExtensionRegistry::class));
    $image = $attachment !== null && \Illuminate\Support\Str::startsWith((string) $attachment->mime, 'image/') ? $attachment : null;
@endphp
<span {{ $attributes }}>{{ $text }}</span>
@if ($image !== null)
    <br><img src="data:{{ $image->mime }};base64,{{ base64_encode((string) \Illuminate\Support\Facades\Storage::disk($image->disk)->get($image->path)) }}" style="max-height: 120px; max-width: 100%;" alt="{{ $field->label }}">
@endif
