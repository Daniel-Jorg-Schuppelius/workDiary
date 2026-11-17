{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Wissensseite anlegen bzw. bearbeiten (Feature 191, MVP-1664). Bearbeitet
  wird gegen die geöffnete Fassung (`base_version`): hat jemand inzwischen
  gespeichert, meldet das Speichern einen Konflikt statt still zu
  überschreiben. Variablen: $page|null, $spaces, $selectedSpace,
  $selectedSection, $parent, $kind, $title, $canApprove, $collectionOptions.
--}}
@php
    $isEdit = $page !== null;
    $tagNames = $isEdit ? $page->tags->pluck('name')->implode(', ') : '';
@endphp
<x-modal
    :title="$isEdit ? __('knowledge_pages.action.edit') : ($kind === \App\Enums\Knowledge\KnowledgePageKind::Guide ? __('knowledge_pages.action.create_guide') : __('knowledge_pages.action.create'))"
    :eyebrow="__('knowledge_pages.title')"
    :icon="$kind->icon()"
    tone="primary"
    size="wide"
    :action="$isEdit ? route('knowledge.pages.update', $page) : route('knowledge.pages.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('knowledge_pages.action.save') : __('knowledge_pages.action.create')">

    @if ($isEdit)
        <input type="hidden" name="base_version" value="{{ $page->version }}">
    @else
        <input type="hidden" name="kind" value="{{ $kind->value }}">
        @if ($parent !== null)
            <input type="hidden" name="parent" value="{{ $parent->sqid }}">
            <input type="hidden" name="space" value="{{ $parent->space?->sqid }}">
        @endif
    @endif

    <x-form-group :legend="__('knowledge_pages.form.page')" icon="article" tone="primary" cols="2">
        <x-input-field name="title" :label="__('knowledge_pages.field.title')" required minlength="2" maxlength="180" span="2" :value="old('title', $isEdit ? $page->title : $title)" />
        @if (! $isEdit && $parent === null)
            <x-select-field name="space" :label="__('knowledge_pages.field.space')" required>
                @foreach ($spaces as $optionSpace)
                    <option value="{{ $optionSpace->sqid }}" @selected(old('space', $selectedSpace) === $optionSpace->sqid)>{{ $optionSpace->title }}</option>
                @endforeach
            </x-select-field>
            <x-select-field name="section" :label="__('knowledge_pages.field.section')" :hint="__('knowledge_pages.form.section_hint')">
                <option value="">{{ __('knowledge_pages.form.no_section') }}</option>
                @foreach ($spaces as $optionSpace)
                    @foreach ($optionSpace->sections as $optionSection)
                        <option value="{{ $optionSection->sqid }}" @selected(old('section', $selectedSection) === $optionSection->sqid)>{{ $optionSpace->title }} › {{ $optionSection->title }}</option>
                    @endforeach
                @endforeach
            </x-select-field>
        @elseif ($parent !== null)
            <p class="text-sm text-muted md:col-span-2">{{ __('knowledge_pages.form.child_of', ['title' => $parent->title]) }}</p>
        @endif
        <x-input-field name="tags" :label="__('knowledge_pages.field.tags')" maxlength="500" span="2" :value="old('tags', $tagNames)" :hint="__('knowledge_pages.form.tags_hint')" />
        @if (($collectionOptions ?? []) !== [])
            <x-select-field name="collection" :label="__('collections.field.collection')" span="2">
                <option value="">—</option>
                @foreach ($collectionOptions as $row)
                    @continue($row['collection']->isArchived())
                    <option value="{{ $row['collection']->sqid }}">{{ str_repeat('– ', $row['depth'] - 1) }}{{ $row['collection']->title }}</option>
                @endforeach
            </x-select-field>
        @endif
    </x-form-group>

    <x-form-group :legend="$kind === \App\Enums\Knowledge\KnowledgePageKind::Guide ? __('knowledge_pages.form.intro') : __('knowledge_pages.field.body')" icon="notes" tone="ghost" cols="1">
        <x-textarea-field name="body" :label="__('knowledge_pages.field.body')" rows="16" :maxlength="\App\Models\Knowledge\KnowledgePage::MAX_BODY_LENGTH"
                          class="font-mono text-sm" :value="old('body', $isEdit ? $page->body : '')" :hint="__('knowledge_pages.form.body_hint')" />
        @include('knowledge-pages._markdown_help')
        <x-input-field name="note" :label="__('knowledge_pages.field.note')" maxlength="500" :hint="__('knowledge_pages.form.note_hint')" :value="old('note')" />
    </x-form-group>

    <x-form-group :legend="__('knowledge_pages.form.attachments')" icon="attach_file" tone="ghost" cols="1">
        <label class="form-control" for="kp-attachments">
            <span class="label-text">{{ __('knowledge_pages.form.attachments_label') }}</span>
            <input id="kp-attachments" type="file" name="attachments[]" multiple
                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.log,.zip,.docx,.xlsx"
                   class="file-input file-input-bordered w-full" />
        </label>
        @error('attachments.*')<p class="text-sm text-error">{{ $message }}</p>@enderror
        <p class="text-xs text-muted">{{ __('knowledge_pages.form.attachments_hint', ['mb' => \App\Services\Attachments\FileAttacher::maxMb()]) }}</p>
    </x-form-group>

    @if ($canApprove)
        <x-checkbox-field name="approve" :label="__('knowledge_pages.form.approve')" :hint="__('knowledge_pages.form.approve_hint')" :checked="(bool) old('approve', false)" />
    @endif
</x-modal>
