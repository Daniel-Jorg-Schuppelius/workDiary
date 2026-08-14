{{--
  Created on   : Mon May 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : demo-banner.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'organization' => null,
])

@php
    /** @var \App\Models\Organization|null $organization */
    $show = $organization !== null && (bool) ($organization->is_demo ?? false);
@endphp

@if ($show)
    <div role="status"
         class="border-b border-warning/40 bg-warning/15 px-4 py-2 text-xs text-warning-content"
         data-demo-banner>
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2">
            <span class="inline-flex items-center gap-2 font-medium">
                <x-icon name="science" class="text-base" />
                {{ __('Dies ist ein Demo-Mandant. Daten sind beispielhaft und können jederzeit zurückgesetzt werden.') }}
            </span>
            @if ($organization->demo_seeded_at)
                <span class="opacity-70">
                    {{ __('Seed: :at', ['at' => $organization->demo_seeded_at->translatedFormat('d.m.Y H:i')]) }}
                </span>
            @endif
        </div>
    </div>
@endif
