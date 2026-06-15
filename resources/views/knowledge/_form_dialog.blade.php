{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog Wissensartikel (in #entry-modal geladen).
  Variablen: $article (KnowledgeArticle|null),
             $linkKind/$linkId (string|null — Auto-Verknüpfung beim Anlegen
             aus einem Auftrag/Asset heraus, Sqid),
             $prefill (array{title?: string, problem?: string})
--}}
@php
    $isEdit = $article !== null;
    $tagNames = $isEdit ? $article->tags->pluck('name')->implode(', ') : '';
@endphp

<x-modal
    :title="$isEdit ? __('knowledge.action.edit') : __('knowledge.action.create')"
    :eyebrow="__('knowledge.title.index')"
    icon="school"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('knowledge.update', $article) : route('knowledge.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('knowledge.action.save') : __('knowledge.action.create')">

    @unless ($isEdit)
        @if ($linkKind !== null && $linkId !== null)
            <input type="hidden" name="link_kind" value="{{ $linkKind }}">
            <input type="hidden" name="link_id" value="{{ $linkId }}">
        @endif
    @endunless

    <x-form-group :legend="__('knowledge.title.index')" icon="school" tone="primary" cols="2">
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('knowledge.field.title') }} *</span>
            <input type="text" name="title" required minlength="3" maxlength="180"
                   class="input input-bordered w-full"
                   value="{{ old('title', $article?->title ?? ($prefill['title'] ?? '')) }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('knowledge.field.category') }}</span>
            <input type="text" name="category" maxlength="80"
                   class="input input-bordered w-full"
                   value="{{ old('category', $article?->category) }}"
                   placeholder="{{ __('knowledge.hint.category') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('knowledge.field.tags') }}</span>
            <input type="text" name="tags" maxlength="500"
                   class="input input-bordered w-full"
                   value="{{ old('tags', $tagNames) }}"
                   placeholder="{{ __('knowledge.hint.tags') }}">
        </label>
    </x-form-group>

    <x-form-group :legend="__('knowledge.field.problem')" icon="report_problem" tone="warning" cols="1">
        <label class="form-control">
            <span class="label-text">{{ __('knowledge.field.problem') }} *</span>
            <textarea name="problem" rows="4" required maxlength="10000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('knowledge.hint.problem') }}">{{ old('problem', $article?->problem ?? ($prefill['problem'] ?? '')) }}</textarea>
        </label>
    </x-form-group>

    <x-form-group :legend="__('knowledge.field.solution')" icon="lightbulb" tone="success" cols="1">
        <label class="form-control">
            <span class="label-text">{{ __('knowledge.field.solution') }} *</span>
            <textarea name="solution" rows="6" required maxlength="20000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('knowledge.hint.solution') }}">{{ old('solution', $article?->solution) }}</textarea>
        </label>
    </x-form-group>

    <x-form-group :legend="__('Anhänge')" icon="attach_file" tone="ghost" cols="1">
        @if ($isEdit && $article->attachments->isNotEmpty())
            <ul class="space-y-1 text-sm">
                @foreach ($article->attachments as $att)
                    <li class="flex items-center gap-2 text-base-content/70">
                        <x-icon :name="$att->isImage() ? 'image' : 'attach_file'" class="text-base-content/50" />
                        <span class="truncate">{{ $att->original_name }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        <label class="form-control">
            <span class="label-text">{{ __('Bilder oder Dokumente hinzufügen') }}</span>
            <input type="file" name="attachments[]" multiple
                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.log,.zip,.docx,.xlsx"
                   class="file-input file-input-bordered w-full" />
        </label>
        @error('attachments.*')<p class="text-sm text-error">{{ $message }}</p>@enderror
        <p class="text-xs text-base-content/50">{{ __('Max. 25 MB pro Datei. Verwalten/Löschen auf der Artikelseite.') }}</p>
    </x-form-group>
</x-modal>
