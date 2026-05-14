@props([
    'showRoute' => null,
    'showParams' => [],
    'editRoute' => null,
    'editParams' => [],
    'deleteRoute' => null,
    'deleteParams' => [],
    'deleteConfirm' => null,
    'size' => 'xs',
])

@php
    $confirm = $deleteConfirm ?? __('Wirklich löschen?');
    $btnSize = match ($size) {
        'sm' => 'btn-sm',
        'md' => '',
        default => 'btn-xs',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1']) }}>
    @if ($showRoute)
        <a href="{{ route($showRoute, $showParams) }}"
           class="btn {{ $btnSize }} btn-ghost"
           aria-label="{{ __('Anzeigen') }}">
            {{ __('Anzeigen') }}
        </a>
    @endif

    @isset($before)
        {{ $before }}
    @endisset

    @if ($editRoute)
        <a href="{{ route($editRoute, $editParams) }}"
           class="btn {{ $btnSize }} btn-ghost"
           aria-label="{{ __('Bearbeiten') }}">
            {{ __('Bearbeiten') }}
        </a>
    @endif

    {{ $slot }}

    @if ($deleteRoute)
        <form method="POST"
              action="{{ route($deleteRoute, $deleteParams) }}"
              class="inline"
              onsubmit="return confirm('{{ $confirm }}')">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="btn {{ $btnSize }} btn-ghost text-error"
                    aria-label="{{ __('Löschen') }}">
                {{ __('Löschen') }}
            </button>
        </form>
    @endif
</div>
