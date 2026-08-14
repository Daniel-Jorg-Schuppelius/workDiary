{{--
  Created on   : Mon Jun 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : daisyui.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="join">
        {{-- Zurück --}}
        @if ($paginator->onFirstPage())
            <button type="button" class="join-item btn btn-sm btn-outline" disabled aria-label="{{ __('Zurück') }}">&laquo;</button>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="join-item btn btn-sm btn-outline" aria-label="{{ __('Zurück') }}">&laquo;</a>
        @endif

        {{-- Seiten-Buttons --}}
        @foreach ($elements as $element)
            {{-- "Drei Punkte"-Trenner --}}
            @if (is_string($element))
                <button type="button" class="join-item btn btn-sm btn-ghost no-animation pointer-events-none" disabled>{{ $element }}</button>
            @endif

            {{-- Seiten-Links --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <button type="button" class="join-item btn btn-sm btn-primary no-animation pointer-events-none" aria-current="page">{{ $page }}</button>
                    @else
                        <a href="{{ $url }}" class="join-item btn btn-sm btn-outline" aria-label="{{ __('Gehe zu Seite :page', ['page' => $page]) }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Weiter --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="join-item btn btn-sm btn-outline" aria-label="{{ __('Weiter') }}">&raquo;</a>
        @else
            <button type="button" class="join-item btn btn-sm btn-outline" disabled aria-label="{{ __('Weiter') }}">&raquo;</button>
        @endif
    </nav>
@endif
