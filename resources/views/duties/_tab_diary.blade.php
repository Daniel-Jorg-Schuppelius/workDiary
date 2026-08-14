{{--
  Created on   : Tue May 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tab_diary.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Aufträge (Diary): Karten-Ansicht --}}
<div class="min-h-0 flex-1 overflow-y-auto space-y-3">
    @forelse ($entries as $entry)
        @include('diary._entry_card', ['entry' => $entry, 'filters' => $filters])
    @empty
        <x-card>
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">menu_book</span>' :title="__('Keine Einträge gefunden')">
                @if (! empty($tabFilters))
                    <x-slot:action>
                        <x-icon-btn icon="restart_alt" size="sm" :href="route('duties.index', ['tab' => 'diary'])" show-label>{{ __('Filter zurücksetzen') }}</x-icon-btn>
                    </x-slot:action>
                @endif
            </x-empty-state>
        </x-card>
    @endforelse
</div>
@if ($entries->total() > 0)
    @include('duties._pagination', ['paginator' => $entries])
@endif
