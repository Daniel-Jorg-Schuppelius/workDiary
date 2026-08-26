{{--
  Created on   : Mon May 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : bookmarks.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-card :title="__('Lesezeichen')">
    <x-slot:actions>
        <x-button href="{{ route('bookmarks.index') }}" tone="ghost" size="xs">
            {{ __('Verwalten') }}
        </x-button>
    </x-slot:actions>

    @if ($bookmarks->isEmpty())
        <p class="text-sm text-muted">{{ __('Noch keine Lesezeichen gespeichert.') }}</p>
    @else
        <ul class="flex flex-col gap-1">
            @foreach ($bookmarks as $bookmark)
                <li>
                    <a href="{{ $bookmark->url }}" class="flex items-center gap-2 rounded-md px-2 py-1 text-sm hover:bg-base-200">
                        @if ($bookmark->icon)
                            <x-icon :name="$bookmark->icon" class="text-base-content/70" />
                        @else
                            <x-icon name="bookmark" class="text-base-content/70" />
                        @endif
                        <span class="truncate">{{ $bookmark->label }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
