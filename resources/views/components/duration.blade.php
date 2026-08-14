{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : duration.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- mode: clock | decimal | both --}}
@props([
    'minutes' => 0,
    'mode' => 'both',
    'withUnit' => true,
])
<span {{ $attributes->merge(['class' => 'whitespace-nowrap tabular-nums']) }}>{{ \App\Support\Formats::duration((int) $minutes, $mode, (bool) $withUnit) }}</span>
