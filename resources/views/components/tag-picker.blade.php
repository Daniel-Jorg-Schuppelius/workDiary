@props([
    'tags' => [],
    'selected' => [],
    'recent' => [],
    'name' => 'tag_ids',
    'newName' => 'new_tags',
    'allowCreate' => true,
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
@endphp

<div {{ $attributes->merge(['class' => 'fieldset']) }}
     x-data="tagPicker({
         all: @js($tagPickerAll),
         selectedIds: @js($tagPickerSelected),
         recentIds: @js($tagPickerRecent),
         initialNew: @js($tagPickerNew),
         quickLimit: 8,
         allowCreate: {{ $allowCreate ? 'true' : 'false' }},
     })"
     @click.outside="open = false">

    {{-- Versteckte Felder für den Submit --}}
    <template x-for="id in existingIds" :key="'eid-' + id">
        <input type="hidden" name="{{ $name }}[]" :value="id">
    </template>
    <input type="hidden" name="{{ $newName }}" :value="newNames.join(', ')">

    {{-- Ausgewählte Tags als entfernbare Chips --}}
    <div class="flex flex-wrap gap-2" x-show="selected.length" x-cloak>
        <template x-for="tag in selected" :key="tag.key">
            <span class="badge gap-1"
                  :class="tag.isNew ? 'badge-success' : 'badge-primary'"
                  :style="tag.color ? `background-color:${tag.color};border-color:${tag.color};color:#fff` : ''">
                <span x-text="tag.name"></span>
                <button type="button" class="opacity-70 hover:opacity-100"
                        :aria-label="tag.name + ' {{ __('entfernen') }}'"
                        @click="remove(tag)">&times;</button>
            </span>
        </template>
    </div>

    {{-- Schnellauswahl: zuletzt verwendete Tags --}}
    <div class="flex flex-wrap gap-2 mt-2" x-show="quickPicks.length" x-cloak>
        <template x-for="tag in quickPicks" :key="'q-' + tag.id">
            <button type="button"
                    class="badge badge-outline transition-colors hover:bg-primary hover:border-primary hover:text-primary-content"
                    @click="addExisting(tag)">
                <span x-show="tag.color" class="inline-block w-2 h-2 rounded-full mr-1"
                      :style="`background:${tag.color}`"></span>
                <span x-text="tag.name"></span>
            </button>
        </template>
    </div>

    {{-- Such-/Eingabefeld mit Dropdown --}}
    <div class="relative mt-2">
        <input type="text"
               x-model="query"
               @focus="open = true"
               @input="open = true; highlight = 0"
               @keydown.enter.prevent="enterPressed()"
               @keydown.arrow-down.prevent="move(1)"
               @keydown.arrow-up.prevent="move(-1)"
               @keydown.escape="open = false"
               autocomplete="off"
               class="input input-bordered input-sm w-full"
               placeholder="{{ __('Tag suchen oder neuen Tag eingeben…') }}">

        <ul x-show="open && (filtered.length || canCreate)" x-cloak x-transition.opacity
            class="menu menu-sm absolute z-30 mt-1 w-full max-h-56 flex-nowrap overflow-y-auto rounded-box border border-base-300 bg-base-100 shadow-lg">
            <template x-for="(tag, idx) in filtered" :key="'f-' + tag.id">
                <li>
                    <button type="button"
                            :class="idx === highlight ? 'active' : ''"
                            @mouseenter="highlight = idx"
                            @click="addExisting(tag)">
                        <span x-show="tag.color" class="inline-block w-2 h-2 rounded-full"
                              :style="`background:${tag.color}`"></span>
                        <span x-text="tag.name"></span>
                    </button>
                </li>
            </template>
            <li x-show="canCreate">
                <button type="button" class="text-success" @click="createNew()">
                    <span>{{ __('Neu anlegen:') }} „<span x-text="query.trim()"></span>"</span>
                </button>
            </li>
        </ul>
    </div>

    <p class="text-xs text-base-content/50 mt-1">
        {{ __('Auf einen Tag klicken oder tippen, um zu suchen bzw. neue Tags anzulegen.') }}
    </p>
</div>
