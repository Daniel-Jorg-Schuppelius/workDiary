{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : tag-picker-ai.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    <x-tag-picker-ai> — KI-Tagvorschläge INNERHALB eines tagPicker-Scopes
    (Feature 143, MVP-711). Rendert nur, wenn die Picker-Konfiguration eine
    suggestUrl trägt (Capability nutzbar). Vorschlags-Chips sind bestehende
    Tags der Organisation; ein Klick übernimmt sie wie die Schnellauswahl
    (addExisting) — nie Auto-Apply, keine Tag-Neuanlage durch die KI.
--}}
<div class="mt-2 space-y-1" x-show="canSuggest" x-cloak>
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" class="btn btn-xs btn-outline btn-info gap-1"
                @click="suggest()" :disabled="suggesting">
            <x-icon name="auto_awesome" />
            <span>{{ __('ai.suggestion.suggest_tags') }}</span>
        </button>
        <template x-for="tag in suggestions" :key="tag.id">
            <button type="button"
                    class="badge badge-outline badge-info transition-colors hover:bg-info hover:text-info-content"
                    @click="acceptSuggestion(tag)">
                <span x-show="tag.color" class="inline-block w-2 h-2 rounded-full mr-1"
                      :style="dotStyle(tag)"></span>
                <span x-text="tag.name"></span>
            </button>
        </template>
    </div>
    <p class="text-xs text-muted" x-show="hasSuggestNotice" x-text="suggestNotice" x-cloak></p>
    <p class="text-xs text-muted" x-show="hasSuggestions" x-cloak>{{ __('ai.suggestion.tags_hint') }}</p>
</div>
