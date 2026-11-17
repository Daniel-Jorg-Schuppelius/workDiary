{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Markdown-Dateien als Wissensseiten übernehmen (Feature 191, MVP-1664).
  Obsidian und OneNote laufen über den Import im Einstieg „Wissen“ mit dem
  Ziel „Wissensseite“. Variablen: $spaces (mit Rubriken).
--}}
<x-modal
    :title="__('knowledge_pages.import.title')"
    :eyebrow="__('knowledge_pages.title')"
    icon="upload_file"
    tone="primary"
    :action="$spaces->isNotEmpty() ? route('knowledge.pages.import.store') : null"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="$spaces->isNotEmpty() ? __('knowledge_pages.import.start') : null">
    <p class="text-sm text-muted">{{ __('knowledge_pages.import.intro', ['max' => \App\Http\Controllers\Knowledge\KnowledgePageImportController::MAX_FILES]) }}</p>
    @if ($spaces->isEmpty())
        <x-empty-state compact icon="menu_book" :title="__('knowledge_pages.empty.spaces')" />
    @else
        <x-select-field name="space" :label="__('knowledge_pages.field.space')" required>
            @foreach ($spaces as $optionSpace)
                <option value="{{ $optionSpace->sqid }}">{{ $optionSpace->title }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="section" :label="__('knowledge_pages.field.section')">
            <option value="">{{ __('knowledge_pages.form.no_section') }}</option>
            @foreach ($spaces as $optionSpace)
                @foreach ($optionSpace->sections as $optionSection)
                    <option value="{{ $optionSection->sqid }}">{{ $optionSpace->title }} › {{ $optionSection->title }}</option>
                @endforeach
            @endforeach
        </x-select-field>
        <label class="form-control" for="kp-import-files">
            <span class="label-text">{{ __('knowledge_pages.import.files') }}</span>
            <input id="kp-import-files" type="file" name="files[]" multiple accept=".md,.markdown,.txt,text/markdown,text/plain" class="file-input file-input-bordered w-full" required />
        </label>
        @error('files')<p class="text-sm text-error">{{ $message }}</p>@enderror
        @error('files.*')<p class="text-sm text-error">{{ $message }}</p>@enderror
        <p class="text-xs text-muted">{{ __('knowledge_pages.import.other_sources') }}</p>
    @endif
</x-modal>
