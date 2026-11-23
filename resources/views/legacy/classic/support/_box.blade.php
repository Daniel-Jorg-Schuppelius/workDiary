{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _box.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Symbol einer Zeilenaktion (Symbolschrift der App) oder Statuspunkt in den Farben der Altportal-Kästchen. Erwartet: $kind; optional $label --}}
@php
    $icon = ['view' => 'visibility', 'edit' => 'edit', 'delete' => 'delete', 'appointment' => 'event', 'customer' => 'person'][$kind] ?? null;
@endphp
@if ($icon !== null)
    <x-icon :name="$icon" />
@else
    <span class="kp-dot kp-dot--{{ $kind }}" @isset($label) role="img" aria-label="{{ $label }}" title="{{ $label }}" @else aria-hidden="true" @endisset></span>
@endif
