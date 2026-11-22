{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _pager.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Seitenwechsel, nur wenn eine Liste über die Seitengröße hinausgeht. Erwartet: $paginator --}}
@if ($paginator->hasPages())
    <p class="kp-pager">
        @if ($paginator->onFirstPage()) &laquo; @else <a href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo;</a> @endif
        {{ __('legacy_classic.page', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}
        @if ($paginator->hasMorePages()) <a href="{{ $paginator->nextPageUrl() }}" rel="next">&raquo;</a> @else &raquo; @endif
    </p>
@endif
