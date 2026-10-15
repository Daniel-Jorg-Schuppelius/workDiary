{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : collapsible-section.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Aufklappbarer Seitenabschnitt (Feature 165, MVP-1192): natives
  <details>/<summary>, damit Tastatur und Screenreader ohne Zusatzlogik
  funktionieren. `key` merkt den Zustand je Person im Browser
  (resources/js/collapsible-section.js, localStorage, nie serverseitig);
  ohne `key` gilt nur `open`.
--}}
@props([
    'title',
    'key' => null,
    'open' => false,
    'icon' => 'unfold_more',
    'count' => null,
])
<details {{ $attributes->merge(['class' => 'wd-collapsible group rounded-box border border-base-300 bg-base-100 shadow-xs']) }}
         @if ($key !== null) data-collapsible="{{ $key }}" @endif
         @if ($open) open @endif>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 text-sm font-semibold marker:content-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
        <span class="flex min-w-0 items-center gap-2">
            <x-icon :name="$icon" class="text-base text-muted" />
            <span class="truncate">{{ $title }}</span>
            @if ($count !== null)
                <span class="badge badge-ghost badge-sm">{{ $count }}</span>
            @endif
        </span>
        <x-icon name="expand_more" class="shrink-0 text-base transition-transform group-open:rotate-180" />
    </summary>
    <div class="space-y-3 border-t border-base-300 p-3 sm:p-4">
        {{ $slot }}
    </div>
</details>
