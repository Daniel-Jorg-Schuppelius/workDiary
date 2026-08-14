{{--
  Created on   : Fri May 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : global-search.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Globale Suche / Command-Palette.
    Wird per Button im Header oder Cmd/Ctrl+K geöffnet. Die Treffer werden per
    fetch() von /api/internal/search geladen und gruppiert dargestellt.
--}}
<dialog id="global-search-dialog" class="modal modal-top sm:modal-middle">
    <div class="modal-box max-w-2xl p-0 overflow-hidden" data-global-search-root>
        <div class="flex items-center gap-2 border-b border-base-300 px-4 py-3">
            <x-icon name="search" class="text-base-content/60" />
            <input type="search"
                   data-global-search-input
                   class="grow bg-transparent outline-none text-sm placeholder:text-base-content/40"
                   placeholder="{{ __('Suche nach Kunden, Projekten, Spesen, Reisen, Mitarbeitern …') }}"
                   autocomplete="off"
                   aria-label="{{ __('Suchbegriff') }}" />
            <kbd class="kbd kbd-xs">ESC</kbd>
        </div>
        <div data-global-search-status class="px-4 py-2 text-xs text-base-content/60 border-b border-base-200 hidden"></div>
        <div data-global-search-results class="max-h-[60vh] overflow-y-auto py-2">
            <div data-global-search-hint class="px-4 py-8 text-center text-sm text-base-content/50">
                {{ __('Tippe mindestens 2 Zeichen, um Ergebnisse zu sehen.') }}
            </div>
        </div>
        <div class="border-t border-base-300 px-4 py-2 text-[0.65rem] uppercase tracking-wider text-base-content/40 flex items-center justify-between">
            <span>{{ __('Globale Suche') }}</span>
            <span class="flex items-center gap-1">
                <kbd class="kbd kbd-xs">↑</kbd><kbd class="kbd kbd-xs">↓</kbd>
                <span>{{ __('Navigieren') }}</span>
                <span class="mx-1 opacity-50">·</span>
                <kbd class="kbd kbd-xs">↵</kbd>
                <span>{{ __('Öffnen') }}</span>
            </span>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>
