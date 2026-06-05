{{-- Sprachumschalter — eine Quelle für alle Platzierungen.
     Speist sich aus App\Support\Locales::enabled() (zentrale Registry).
     variant="dropdown" (Default): kompaktes Dropdown mit Flaggen-Trigger.
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
                    <span class="text-sm leading-none">{{ $meta['flag'] }}</span>
                    <span>{{ $meta['native'] }}</span>
                </button>
            </form>
        @endforeach
    </div>
@else
    <div class="dropdown dropdown-end">
        <label tabindex="0" class="btn btn-sm btn-ghost btn-square"
               title="{{ __('Sprache wechseln') }}" aria-label="{{ __('Sprache wechseln') }}">
            <span class="text-base leading-none">{{ \App\Support\Locales::flag($current) }}</span>
        </label>
        <ul tabindex="0" class="dropdown-content header-dropdown-panel header-menu-list menu z-50 w-[min(12rem,calc(100vw-1rem))] rounded-box border border-base-300 bg-base-100 p-1 shadow">
            @foreach ($locales as $code => $meta)
                <li>
                    <form method="POST" action="{{ route('locale.switch', $code) }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 {{ $current === $code ? 'active' : '' }}"
                                @if ($current === $code) aria-current="true" @endif>
                            <span class="text-base leading-none">{{ $meta['flag'] }}</span>
                            <span>{{ $meta['native'] }}</span>
                            @if ($current === $code)
                                <span class="ml-auto opacity-60">•</span>
                            @endif
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
@endif
