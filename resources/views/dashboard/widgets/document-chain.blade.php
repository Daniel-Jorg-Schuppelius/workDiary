{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : document-chain.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Abzurechnen und nachzufassen“ (MVP-1057) — Daten: DocumentChainWidget.
--}}
<x-card :title="__('invoicing.chain.title')" icon="conversion_path">
    <x-slot:actions>
        <x-button href="{{ route('billing.chain') }}" tone="ghost" size="xs">{{ __('invoicing.chain.open') }}</x-button>
    </x-slot:actions>
    @php
        $open = collect($groups)->filter(fn (array $g): bool => $g['count'] > 0);
    @endphp
    @if ($open->isEmpty())
        <x-empty-state compact icon="check_circle" :title="__('invoicing.chain.empty_title')" :message="__('invoicing.chain.empty')" />
    @else
        <ul class="divide-y divide-base-200">
            @foreach ($open as $group)
                <li class="flex items-center gap-2 py-1.5">
                    <x-icon :name="$group['icon']" class="text-muted" />
                    <a href="{{ route('billing.chain') }}#{{ $group['key'] }}" class="link link-hover flex-1 min-w-0 truncate">{{ $group['label'] }}</a>
                    <span class="badge badge-sm">{{ $group['count'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
