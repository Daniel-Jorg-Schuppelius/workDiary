{{--
  Created on   : Mon Jun 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : tag-picker.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'tags' => [],
    'selected' => [],
    'recent' => [],
    'name' => 'tag_ids',
    'newName' => 'new_tags',
    'allowCreate' => true,
    'suggestFrom' => null,   // CSS-Selektor des Freitextfelds im selben <form> → KI-Tagvorschläge (Feature 143)
    'customerFrom' => null,  // optional: Selektor des Kundenfelds (Sqid) als schwacher Prior
])

{{--
    <x-tag-picker> — einzeilige, Chip-basierte Tag-Auswahl (Vorbild: Diary-Formular).

    Ersetzt das native <select multiple> (vertikale Listbox) durch eine kompakte
    Auswahl mit entfernbaren Chips, Schnellauswahl und Such-/Anlege-Feld.
    Submit-Format: bestehende Tags als `tag_ids[]` (Sqid), neue als `new_tags` (kommasepariert).

    Props:
      - tags        : iterierbare Tag-Liste (Objekte mit ->sqid/->name/->color)
      - selected    : Array bereits gewählter Tag-Sqids
      - recent      : Array zuletzt verwendeter Tag-Sqids (Schnellauswahl)
      - name        : Feldname der bestehenden Tags (Default tag_ids)
      - newName     : Feldname neuer Tags (Default new_tags)
      - allowCreate : neue Tags anlegen erlauben (Default true)
      - suggestFrom : Selektor des Freitextfelds → Button „KI-Tags vorschlagen" + Chips,
                      nur wenn die Capability classification.tag_suggest nutzbar ist (MVP-711)
      - customerFrom: Selektor des Kundenfelds (Sqid) — Tags dieses Kunden stehen vorn
--}}

@php
    $tagPickerAll = collect($tags)->map(fn ($t) => [
        'id' => $t->sqid,
        'name' => $t->name,
        'color' => $t->color,
    ])->values()->all();
    $tagPickerSelected = collect(old($name, $selected))
        ->map(fn ($v) => (string) $v)->filter()->unique()->values()->all();
    $tagPickerRecent = collect($recent)
        ->map(fn ($v) => (string) $v)->values()->all();
    $tagPickerNew = collect(preg_split('/[,;\n]+/', (string) old($newName, '')) ?: [])
        ->map(fn ($v) => trim((string) $v))->filter()->values()->all();
    $tagSuggestUrl = $suggestFrom !== null
        && app(\App\Services\Ai\Suggestions\SuggestionViewData::class)->capabilityUsable(\App\Services\Ai\Suggestions\ClassificationSuggestionService::CAPABILITY)
        ? route('ai.suggest.tags')
        : null;
    $tagPickerConfig = ['all' => $tagPickerAll, 'selectedIds' => $tagPickerSelected, 'recentIds' => $tagPickerRecent, 'initialNew' => $tagPickerNew, 'quickLimit' => 8, 'allowCreate' => (bool) $allowCreate, 'suggestUrl' => $tagSuggestUrl, 'textSelector' => $suggestFrom, 'customerSelector' => $customerFrom];
@endphp

<div {{ $attributes->merge(['class' => 'fieldset']) }}
     x-data="tagPicker"
     data-config="{{ json_encode($tagPickerConfig) }}"
     @click.outside="close()">

    {{-- Versteckte Felder für den Submit --}}
    <template x-for="id in existingIds" :key="id">
        <input type="hidden" name="{{ $name }}[]" :value="id">
    </template>
    <input type="hidden" name="{{ $newName }}" :value="newNamesText">

    {{-- Ausgewählte Tags als entfernbare Chips --}}
    <div class="flex flex-wrap gap-2" x-show="hasSelected" x-cloak>
        <template x-for="tag in selected" :key="tag.key">
            <span class="badge gap-1"
                  :class="chipClass(tag)"
                  :style="chipStyle(tag)">
                <span x-text="tag.name"></span>
                <button type="button" class="opacity-70 hover:opacity-100"
                        aria-label="{{ __('Tag entfernen') }}"
                        @click="remove(tag)">&times;</button>
            </span>
        </template>
    </div>

    {{-- Schnellauswahl: zuletzt verwendete Tags --}}
    <div class="flex flex-wrap gap-2 mt-2" x-show="hasQuickPicks" x-cloak>
        <template x-for="tag in quickPicks" :key="tag.id">
            <button type="button"
                    class="badge badge-outline transition-colors hover:bg-primary hover:border-primary hover:text-primary-content"
                    @click="addExisting(tag)">
                <span x-show="tag.color" class="inline-block w-2 h-2 rounded-full mr-1"
                      :style="dotStyle(tag)"></span>
                <span x-text="tag.name"></span>
            </button>
        </template>
    </div>

    @if ($tagSuggestUrl !== null)
        <x-tag-picker-ai />
    @endif

    {{-- Such-/Eingabefeld mit Dropdown --}}
    <div class="relative mt-2">
        <input type="text"
               x-model="query"
               @focus="openMenu()"
               @input="onInput()"
               @keydown.enter.prevent="enterPressed()"
               @keydown.arrow-down.prevent="move(1)"
               @keydown.arrow-up.prevent="move(-1)"
               @keydown.escape="close()"
               autocomplete="off"
               class="input input-bordered input-sm w-full"
               aria-label="{{ __('Tag suchen oder neuen Tag eingeben…') }}"
               placeholder="{{ __('Tag suchen oder neuen Tag eingeben…') }}">

        <ul x-show="showMenu" x-cloak x-transition.opacity
            class="menu menu-sm absolute z-30 mt-1 w-full max-h-56 flex-nowrap overflow-y-auto rounded-box border border-base-300 bg-base-100 shadow-lg">
            <template x-for="(tag, idx) in filtered" :key="tag.id">
                <li>
                    <button type="button"
                            :class="optionClass(idx)"
                            @mouseenter="setHighlight(idx)"
                            @click="addExisting(tag)">
                        <span x-show="tag.color" class="inline-block w-2 h-2 rounded-full"
                              :style="dotStyle(tag)"></span>
                        <span x-text="tag.name"></span>
                    </button>
                </li>
            </template>
            <li x-show="canCreate">
                <button type="button" class="text-success" @click="createNew()">
                    <span>{{ __('Neu anlegen:') }} „<span x-text="queryTrimmed"></span>"</span>
                </button>
            </li>
        </ul>
    </div>

    <p class="text-xs text-muted mt-1">
        {{ __('Auf einen Tag klicken oder tippen, um zu suchen bzw. neue Tags anzulegen.') }}
    </p>
</div>
