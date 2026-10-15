{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _focus-tile.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Eine Kachel des Arbeitsbereich-Dialogs: Symbol und Titel in einer Zeile,
    Beschreibung darunter; „Aktiv“ bleibt oben rechts und bekommt in der
    Kopfzeile Platz freigehalten. Erwartet $focus (key, label, description,
    icon) und $active.
--}}
<form method="POST" action="{{ route('me.focus.switch', $focus['key']) }}" class="m-0">
    @csrf
    <button type="submit"
            class="group relative flex h-full w-full flex-col gap-2 rounded-box border p-3 text-left transition hover:-translate-y-0.5 hover:shadow-md
                   {{ $active ? 'border-primary bg-primary/10 ring-1 ring-primary' : 'border-base-300 bg-base-100 hover:border-primary/40 hover:bg-base-200' }}">
        @if ($active)
            <x-status-badge tone="primary" class="absolute right-3 top-3">{{ __('scope.focus.active') }}</x-status-badge>
        @endif
        <span class="flex items-center gap-3 {{ $active ? 'pr-16' : '' }}">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-field {{ $active ? 'bg-primary text-primary-content' : 'bg-base-200 text-primary' }}">
                <x-icon :name="$focus['icon']" class="text-2xl" />
            </span>
            <span class="font-semibold leading-tight">{{ $focus['label'] }}</span>
        </span>
        <span class="text-xs leading-snug text-base-content/65">{{ $focus['description'] }}</span>
    </button>
</form>
