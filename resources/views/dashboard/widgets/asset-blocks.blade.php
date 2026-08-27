{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : asset-blocks.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Gesperrte Objekte" — Daten: AssetBlocksWidget.
--}}
<x-card :title="__('Gesperrte Objekte')" icon="block" :count="$count">
    <x-slot:actions>
        <x-button href="{{ route('asset-compliance.index') }}" tone="ghost" size="xs">{{ __('Alle →') }}</x-button>
    </x-slot:actions>

    @if ($blocks->isEmpty())
        <x-empty-state compact icon="check_circle"
                       :title="__('Keine Sperren')" :message="__('Derzeit ist kein Objekt gesperrt.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($blocks as $block)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-error/40 bg-error/5 px-3 py-2">
                    <span class="min-w-0 truncate">{{ $block->asset?->name ?? '—' }}</span>
                    <x-status-badge size="xs" tone="error">{{ $block->reason->label() }}</x-status-badge>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
