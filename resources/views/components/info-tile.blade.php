{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : info-tile.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props(['label'])

{{-- Beschriftete Kachel einer Detailansicht (Von/Bis/Kunde …); `after` steht unter dem Wert, etwa ein Link. --}}
<div {{ $attributes->class(['rounded-xl border border-base-300 bg-base-200 px-4 py-3']) }}>
    <p class="mb-1 text-xs text-muted">{{ $label }}</p>
    <p class="text-base-content">{{ $slot }}</p>
    {{ $after ?? '' }}
</div>
