{{--
    <x-modal> — der zentrale Dialog-Wrapper. Ersetzt <x-dialog> und <x-form-dialog>.

    Zwei Render-Modi:

    1) embedded=true (Default): rendert nur den .wd-dialog-Block (Header/Body/Footer).
       Wird in einen externen <dialog>-Host (z. B. #entry-modal) per AJAX geladen.
       Die `_form_dialog.blade.php`-Partials nutzen diesen Modus.

    2) embedded=false: rendert ein vollständiges <dialog id> + .modal-box + Backdrop.
       Wird für ad-hoc-Dialoge verwendet (action-confirm, action-notify, shift-dialog,
       quick-timesheet, shift-type-manager).

    Wenn `action` gesetzt ist → Body wird in <form method action> mit @csrf/@method
    eingewickelt; Default-Footer enthält Submit + Cancel.
    Sonst → kein <form>; Default-Footer enthält „Schließen".

    Slots:
      - default          → Body-Inhalt
      - header (named)   → zusätzlicher Header-Inhalt unter Title
      - actions (named)  → überschreibt Default-Footer-Buttons
      - footerExtra      → links-ausgerichtete Buttons im Footer (z. B. Löschen)
--}}
@props([
    'id' => null,
    'title' => null,
    'eyebrow' => null,
    'icon' => null,
    'badge' => null,
    'badgeTone' => 'ghost',
    'tone' => 'primary',
    'size' => 'lg',
    'embedded' => true,
    'action' => null,
    'method' => 'POST',
    'enctype' => null,
    'autocomplete' => 'on',
    'submitLabel' => null,
    'cancelLabel' => null,
    'closeLabel' => null,
    'submitClass' => 'btn-primary',
    'hideFooter' => false,
    'formClass' => '',
    'formId' => null,
    'formData' => [],
    'headerId' => null,
    'iconId' => null,
    'iconWrapId' => null,
    'titleId' => null,
    'bodyId' => null,
])

@php
    $accent = [
        'primary' => 'from-primary/15 via-primary/5 to-transparent',
        'success' => 'from-success/15 via-success/5 to-transparent',
        'warning' => 'from-warning/15 via-warning/5 to-transparent',
        'error'   => 'from-error/15 via-error/5 to-transparent',
        'info'    => 'from-info/15 via-info/5 to-transparent',
        'ghost'   => 'from-base-300/40 via-base-200/30 to-transparent',
    ][$tone] ?? 'from-primary/15 via-primary/5 to-transparent';

    $iconAccent = [
        'primary' => 'bg-primary/15 text-primary',
        'success' => 'bg-success/15 text-success',
        'warning' => 'bg-warning/15 text-warning',
        'error'   => 'bg-error/15 text-error',
        'info'    => 'bg-info/15 text-info',
        'ghost'   => 'bg-base-300 text-base-content/70',
    ][$tone] ?? 'bg-primary/15 text-primary';

    $sizeClass = [
        'sm'  => 'max-w-sm',
        'md'  => 'max-w-md',
        'lg'  => 'max-w-3xl',
        'xl'  => 'max-w-5xl',
        '2xl' => 'max-w-7xl',
    ][$size] ?? 'max-w-3xl';

    $methodUpper = strtoupper($method ?? 'POST');
    $isSpoofed   = in_array($methodUpper, ['PUT', 'PATCH', 'DELETE'], true);
    $formMethod  = $isSpoofed ? 'POST' : $methodUpper;
    $hasForm     = $action !== null;

    $submitLbl = $submitLabel ?? __('Speichern');
    $cancelLbl = $cancelLabel ?? __('Abbrechen');
    $closeLbl  = $closeLabel  ?? __('Schließen');

    $hasActions     = isset($actions);
    $hasFooterExtra = isset($footerExtra);
    $hasHeaderExtra = isset($header);
    $hasAnyHeader   = $title || $eyebrow || $icon || $badge || $hasHeaderExtra;
    $showFooter     = ! $hideFooter && ($hasActions || $hasFooterExtra || $hasForm || ! $embedded);

    // Icon-Prop: wenn Material-Symbols-Name (alphanumerisch + _), via <x-icon> rendern; sonst Raw-HTML/Emoji.
    $iconIsSymbol   = is_string($icon) && $icon !== '' && preg_match('/^[a-z0-9_]+$/', $icon) === 1;
@endphp

@if (! $embedded)
<dialog id="{{ $id }}" class="modal" data-form-dialog>
    <div class="modal-box wd-modal-box {{ $sizeClass }} p-0">
@endif

@if ($hasForm)
    <form
        @if ($formId) id="{{ $formId }}" @endif
        method="{{ $formMethod }}"
        action="{{ $action }}"
        autocomplete="{{ $autocomplete }}"
        @if ($enctype) enctype="{{ $enctype }}" @endif
        class="{{ $formClass }}"
        @foreach ($formData as $fdKey => $fdVal)
            @if (is_bool($fdVal))
                @if ($fdVal) {{ $fdKey }} @endif
            @elseif ($fdVal === null || $fdVal === '')
                {{ $fdKey }}
            @else
                {{ $fdKey }}="{{ $fdVal }}"
            @endif
        @endforeach
    >
        @csrf
        @if ($isSpoofed)
            @method($methodUpper)
        @endif
@endif

<div {{ $attributes->merge(['class' => 'wd-dialog']) }}>
    @if ($hasAnyHeader)
        <header @if ($headerId) id="{{ $headerId }}" @endif
                class="wd-dialog__header sticky top-0 z-10 flex items-start gap-3 border-b border-base-300 bg-linear-to-br {{ $accent }} px-6 py-5 pr-16">
            @if ($icon)
                <div @if ($iconWrapId) id="{{ $iconWrapId }}" @endif
                     class="flex h-10 w-10 shrink-0 items-center justify-center rounded-box {{ $iconAccent }} text-lg">
                    @if ($iconId)
                        <span id="{{ $iconId }}">
                            @if ($iconIsSymbol)
                                <x-icon :name="$icon" />
                            @else
                                {!! $icon !!}
                            @endif
                        </span>
                    @elseif ($iconIsSymbol)
                        <x-icon :name="$icon" />
                    @else
                        {!! $icon !!}
                    @endif
                </div>
            @endif
            <div class="min-w-0 flex-1">
                @if ($eyebrow)
                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-base-content/60">{{ $eyebrow }}</p>
                @endif
                @if ($title)
                    <h2 @if ($titleId) id="{{ $titleId }}" @endif
                        class="font-['Space_Grotesk'] text-xl font-bold text-base-content @if ($eyebrow) mt-1 @endif">{{ $title }}</h2>
                @endif
                @if ($hasHeaderExtra)
                    {{ $header }}
                @endif
            </div>
            @if ($badge)
                <span class="badge badge-sm badge-{{ $badgeTone }} shrink-0">{{ $badge }}</span>
            @endif
            <button type="button" data-entry-modal-close
                    class="wd-dialog__close"
                    aria-label="{{ $closeLbl }}">
                <x-icon name="close" />
            </button>
        </header>
    @endif

    <div @if ($bodyId) id="{{ $bodyId }}" @endif class="wd-dialog__body space-y-4 px-6 py-5">
        {{ $slot }}
    </div>

    @if ($showFooter)
        <footer class="wd-dialog__footer">
            <div class="wd-dialog__footer-extra">
                @if ($hasFooterExtra)
                    {{ $footerExtra }}
                @endif
            </div>
            <div class="wd-dialog__footer-actions">
                @if ($hasActions)
                    {{ $actions }}
                @elseif ($hasForm)
                    <button type="button" class="btn btn-ghost gap-2" data-entry-modal-close>
                        <x-icon name="close" /> {{ $cancelLbl }}
                    </button>
                    <button type="submit" class="btn {{ $submitClass }} gap-2">
                        <x-icon name="check" /> {{ $submitLbl }}
                    </button>
                @else
                    <button type="button" class="btn btn-primary gap-2" data-entry-modal-close>
                        <x-icon name="close" /> {{ $closeLbl }}
                    </button>
                @endif
            </div>
        </footer>
    @endif
</div>

@if ($hasForm)
    </form>
@endif

@if (! $embedded)
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>{{ $closeLbl }}</button>
    </form>
</dialog>
@endif
