{{--
  Created on   : Mon Nov 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _view_switch.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Umschalter zwischen klassischer und neuer Darstellung des Legacy-Bereichs; die Wahl gilt je Browser. Optional: $variant (app|classic) --}}
@php
    $switchClassic = \App\Legacy\Support\LegacyView::isClassic(request());
    $switchBack = request()->getRequestUri();
    $switchModes = [
        \App\Legacy\Support\LegacyView::CLASSIC => ['icon' => 'history', 'label' => __('legacy_classic.switch.classic'), 'active' => $switchClassic],
        \App\Legacy\Support\LegacyView::MODERN => ['icon' => 'auto_awesome', 'label' => __('legacy_classic.switch.modern'), 'active' => ! $switchClassic],
    ];
@endphp
@if (($variant ?? 'app') === 'classic')
    <div class="kp-switch" role="group" aria-label="{{ __('legacy_classic.switch.label') }}">
        @foreach ($switchModes as $switchMode => $switchItem)
            <a href="{{ route('legacy.view', ['mode' => $switchMode, 'back' => $switchBack]) }}" @if ($switchItem['active']) aria-current="true" @endif>
                <x-icon :name="$switchItem['icon']" size="18px" />{{ $switchItem['label'] }}
            </a>
        @endforeach
    </div>
@else
    <div class="join shrink-0" role="group" aria-label="{{ __('legacy_classic.switch.label') }}">
        @foreach ($switchModes as $switchMode => $switchItem)
            <x-icon-btn :icon="$switchItem['icon']" size="sm" :tone="$switchItem['active'] ? 'primary' : 'outline'" class="join-item"
                        :href="route('legacy.view', ['mode' => $switchMode, 'back' => $switchBack])"
                        :aria-current="$switchItem['active'] ? 'true' : null" show-label>{{ $switchItem['label'] }}</x-icon-btn>
        @endforeach
    </div>
@endif
