@props([
    'label'     => null,
    'for'       => null,
    'class'     => '',
    'showLabel' => false,   // Default: kein sichtbares Label.
                            // In Filter-Bars sind Selects/Inputs selbsterklärend
                            // (Default-Option "Alle …") und Höhen gleichen sich an.
                            // Für Form-Bodies explizit `show-label` setzen.
])

<div class="flex flex-col gap-1 {{ $class }}">
    @if ($label && $showLabel)
        <label @if ($for) for="{{ $for }}" @endif
               class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-base-content/60">
            {{ $label }}
        </label>
    @endif
    {{ $slot }}
</div>
