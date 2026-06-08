@props([
    /** Endpoint, an den die Datei per multipart/form-data POSTet wird. */
    'action',
    /** meta_type für den AttachmentController (z. B. logo, logo_dark, avatar). */
    'meta' => null,
    /** Optionale URL einer DELETE-Route (z. B. attachments.destroyMeta). */
    'deleteAction' => null,
    /** Aktuell aktiver Anhang (App\Models\Attachment|null) zur Vorschau. */
    'current' => null,
    /** Anzeigelabel über dem Upload-Feld. */
    'label' => null,
    /** Hilfetext unterhalb des Feldes (z. B. "PNG/JPG bis 2 MB"). */
    'helper' => null,
    /** Maximale Dateigröße in KB für client-seitige Vorprüfung. */
    'maxKb' => 2048,
    /** Whitelist der akzeptierten MIME-Typen für <input accept>. */
    'accept' => 'image/png,image/jpeg,image/webp',
    /** Form-Feldname; muss zu Controller-Validierung passen. */
    'name' => 'file',
])

@php
    /** @var \App\Models\Attachment|null $current */
    $previewUrl = null;
    if ($current !== null) {
        // Signierter Temp-URL aus AttachmentController – funktioniert auch
        // für nicht-public Disks (z. B. `local`).
        $previewUrl = \App\Http\Controllers\AttachmentController::downloadUrl($current);
    }
    $inputId = 'fu_' . uniqid();
@endphp

<div class="wd-file-upload" x-data="fileUpload({{ (int) $maxKb }}, @js(__('Datei ist größer als das Limit.')))">
    @if ($label)
        <label for="{{ $inputId }}" class="wd-file-upload__label">{{ $label }}</label>
    @endif

    <div class="wd-file-upload__row">
        <div class="wd-file-upload__preview">
            @if ($previewUrl)
                <img src="{{ $previewUrl }}" alt="{{ __('Vorschau') }}" class="wd-file-upload__img" />
            @else
                <div class="wd-file-upload__placeholder" aria-hidden="true">
                    <x-icon name="image" />
                </div>
            @endif
        </div>

        <div class="wd-file-upload__controls">
            <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="wd-file-upload__form">
                @csrf
                @if ($meta)
                    <input type="hidden" name="meta_type" value="{{ $meta }}" />
                @endif

                <input
                    type="file"
                    id="{{ $inputId }}"
                    name="{{ $name }}"
                    accept="{{ $accept }}"
                    class="file-input file-input-bordered file-input-sm w-full max-w-xs"
                    @change="onChange($event)"
                    required
                />

                <div class="wd-file-upload__actions">
                    <button type="submit" class="btn btn-primary btn-sm" x-bind:disabled="hasNoFile">
                        <x-icon name="upload" />
                        <span>{{ __('Hochladen') }}</span>
                    </button>
                    <template x-if="fileName">
                        <span class="wd-file-upload__meta" x-text="fileLabel"></span>
                    </template>
                </div>

                <p class="wd-file-upload__error" x-show="error" x-text="error"></p>
            </form>

            @if ($current && $deleteAction)
                <form method="POST" action="{{ $deleteAction }}" class="wd-file-upload__delete"
                      data-confirm-dialog
                      data-confirm-message="{{ __('Bild wirklich entfernen?') }}"
                      data-confirm-icon="delete"
                      data-confirm-tone="error"
                      data-confirm-label="{{ __('Entfernen') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-sm text-error">
                        <x-icon name="delete" />
                        <span>{{ __('Entfernen') }}</span>
                    </button>
                </form>
            @endif

            @if ($helper)
                <p class="wd-file-upload__helper">{{ $helper }}</p>
            @endif

            @error($name)
                <p class="wd-file-upload__error">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
