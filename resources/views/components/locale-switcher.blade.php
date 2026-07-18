{{-- Sprachumschalter — eine Quelle für alle Platzierungen.
     Speist sich aus App\Support\Locales::enabled() (zentrale Registry).
     Design nach ckonverter-Frontend: Globus-Icon mit Badge des aktiven
     Sprachcodes als Trigger, im Menü SVG-Flaggen (<x-locale-flag>) + Autonym.
     variant="dropdown" (Default): kompaktes Dropdown für Header.
     variant="inline": Button-Reihe (Flagge + Autonym) für Panels. --}}
@props(['variant' => 'dropdown'])
@php
    $current = app()->getLocale();
    $locales = \App\Support\Locales::enabled();
@endphp

@if ($variant === 'inline')
    <div class="flex flex-wrap gap-1">
        @foreach ($locales as $code => $meta)
            <form method="POST" action="{{ route('locale.switch', $code) }}">
                @csrf
                <button type="submit"
                        class="btn btn-xs {{ $current === $code ? 'btn-primary' : 'btn-ghost' }} gap-1.5"
                        title="{{ $meta['native'] }}"
                        @if ($current === $code) aria-current="true" @endif>
                    <x-locale-flag :code="$code" :width="18" />
                    <span>{{ $meta['native'] }}</span>
                </button>
            </form>
        @endforeach
    </div>
@else
    <div class="dropdown dropdown-end">
        <label tabindex="0" class="btn btn-sm btn-ghost btn-square"
               title="{{ __('Sprache wechseln') }}" aria-label="{{ __('Sprache wechseln') }}">
            <span class="relative flex items-center justify-center">
                <x-icon name="language" class="text-base" />
                <span class="absolute bottom-0 -right-1 rounded bg-primary px-0.5 text-[10px] font-bold uppercase leading-tight text-primary-content">{{ $current }}</span>
            </span>
        </label>
        <ul tabindex="0" class="dropdown-content header-dropdown-panel header-menu-list menu z-50 w-40 rounded-box border border-base-300 bg-base-100 p-2 shadow">
            @foreach ($locales as $code => $meta)
                <li>
                    <form method="POST" action="{{ route('locale.switch', $code) }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 {{ $current === $code ? 'active' : '' }}"
                                @if ($current === $code) aria-current="true" @endif>
                            <x-locale-flag :code="$code" />
                            <span>{{ $meta['native'] }}</span>
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
@endif
