{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : upload-input.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Mehrfach-Dateifeld (MVP-1074) innerhalb eines Formulars mit
  `data-upload-form`: Formate, wirksame Grenzen (App ∩ PHP), Auswahlliste,
  Fortschritt und Fehler je Datei (resources/js/upload-form.js). Ohne JS
  bleibt es ein normales <input type="file" multiple>.
--}}
@props([
    'purpose' => \App\Enums\Attachments\UploadPurpose::General,
    'name' => 'uploads',
    'label' => null,
    'maxFiles' => null,
    'required' => false,
    // Interne Dialoge sammeln die Fehler im Dialog selbst.
    'showErrors' => true,
])

@php
    /** @var \App\Enums\Attachments\UploadPurpose $purpose */
    $maxFiles ??= $purpose->maxFiles();
    $maxKb = \App\Services\Attachments\FileAttacher::effectiveMaxKb($purpose);
    $totalKb = \App\Services\Attachments\FileAttacher::effectiveTotalKb($purpose);
    $extensions = $purpose->extensions();
    $size = \CommonToolkit\ValueObjects\ByteSize::ofBytes($maxKb * 1024)->format(0);
    $total = \CommonToolkit\ValueObjects\ByteSize::ofBytes($totalKb * 1024)->format(0);
    $id = $attributes->get('id') ?? 'upload_' . $name . '_' . uniqid();
    $bag = view()->shared('errors');
    $messages = $showErrors && $bag instanceof \Illuminate\Support\ViewErrorBag
        ? array_merge($bag->get($name), ...array_values($bag->get($name . '.*')))
        : [];
@endphp

<div class="fieldset" {{ $attributes->except('id') }}>
    <div hidden data-upload-config
         data-max-bytes="{{ $maxKb * 1024 }}"
         data-max-total-bytes="{{ $totalKb * 1024 }}"
         data-max-files="{{ $maxFiles }}"
         data-extensions="{{ implode(',', $extensions) }}"
         data-msg-type="{{ __('uploads.error.type', ['name' => ':name']) }}"
         data-msg-too-large="{{ __('uploads.error.too_large') }}"
         data-msg-count="{{ __('uploads.error.count') }}"
         data-msg-total="{{ __('uploads.error.total') }}"
         data-msg-progress="{{ __('uploads.progress') }}"
         data-msg-failed="{{ __('uploads.error.failed') }}"></div>

    <label class="fieldset-label" for="{{ $id }}">{{ $label ?? __('uploads.label') }}{{ $required ? ' *' : '' }}</label>
    <input id="{{ $id }}" name="{{ $name }}[]" type="file" multiple data-upload-input
           accept="{{ collect($extensions)->map(fn (string $ext): string => '.' . $ext)->implode(',') }}"
           aria-describedby="{{ $id }}_hint" @required($required)
           class="file-input file-input-bordered w-full {{ $messages !== [] ? 'file-input-error' : '' }}">
    <p id="{{ $id }}_hint" class="mt-1 text-xs text-muted">
        {{ __('uploads.hint', ['formats' => strtoupper(implode(', ', $extensions)), 'size' => $size, 'count' => $maxFiles, 'total' => $total]) }}
    </p>
    <ul class="mt-1 list-inside list-disc text-sm" data-upload-list aria-live="polite"></ul>
    <progress class="progress progress-primary mt-2 w-full" max="100" value="0" hidden data-upload-progress></progress>
    <p class="text-xs text-muted" data-upload-status aria-live="polite"></p>
    <ul class="mt-1 list-inside list-disc text-sm text-error" data-upload-errors role="alert" tabindex="-1" hidden></ul>
    @foreach ($messages as $message)
        <p class="text-error text-sm">{{ $message }}</p>
    @endforeach
</div>
