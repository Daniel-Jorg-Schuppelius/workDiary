@props([
    'id',
    'title' => null,
    'eyebrow' => null,
    'icon' => null,
    'badge' => null,
    'badgeTone' => 'ghost',
    'tone' => 'primary',
    'action',
    'method' => 'POST',
    'enctype' => null,
    'submitLabel' => null,
    'cancelLabel' => null,
    'submitClass' => 'btn-primary',
    'autocomplete' => 'on',
])

@php
    $methodUpper = strtoupper($method);
    $isSpoofed = in_array($methodUpper, ['PUT', 'PATCH', 'DELETE'], true);
    $formMethod = $isSpoofed ? 'POST' : $methodUpper;
    $submitLbl = $submitLabel ?? __('Speichern');
    $cancelLbl = $cancelLabel ?? __('Abbrechen');
@endphp

<dialog id="{{ $id }}" class="modal" data-form-dialog>
    <div class="modal-box max-w-3xl p-0 overflow-hidden">
        <form method="{{ $formMethod }}"
              action="{{ $action }}"
              autocomplete="{{ $autocomplete }}"
              @if($enctype) enctype="{{ $enctype }}" @endif>
            @csrf
            @if ($isSpoofed)
                @method($methodUpper)
            @endif

            <x-dialog :title="$title"
                      :eyebrow="$eyebrow"
                      :icon="$icon"
                      :badge="$badge"
                      :badgeTone="$badgeTone"
                      :tone="$tone">
                {{ $slot }}

                <x-slot:actions>
                    <button type="button"
                            class="btn btn-ghost"
                            data-entry-modal-close>
                        {{ $cancelLbl }}
                    </button>
                    <button type="submit" class="btn {{ $submitClass }}">
                        {{ $submitLbl }}
                    </button>
                </x-slot:actions>
            </x-dialog>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>{{ __('Schließen') }}</button>
    </form>
</dialog>
