{{--
  Created on   : Wed Apr 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : daisyui-simple.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="join flex w-full justify-between">
        @if ($paginator->onFirstPage())
            <button type="button" class="join-item btn btn-sm btn-outline" disabled>&laquo; {{ __('Zurück') }}</button>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="join-item btn btn-sm btn-outline btn-primary">&laquo; {{ __('Zurück') }}</a>
        @endif

        <span class="join-item btn btn-sm btn-ghost no-animation pointer-events-none">
            {{ $paginator->currentPage() }}
        </span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="join-item btn btn-sm btn-outline btn-primary">{{ __('Weiter') }} &raquo;</a>
        @else
            <button type="button" class="join-item btn btn-sm btn-outline" disabled>{{ __('Weiter') }} &raquo;</button>
        @endif
    </nav>
@endif
