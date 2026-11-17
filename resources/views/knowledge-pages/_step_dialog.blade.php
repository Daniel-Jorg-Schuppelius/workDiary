{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _step_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Schritt einer Bildanleitung hinzufügen (Feature 191, MVP-1668):
  Bildschirmfoto und Text. Markierungen und Schwärzen folgen im Editor.
  Variablen: $page.
--}}
<x-modal
    :title="__('knowledge_pages.guide.add_step')"
    :eyebrow="$page->title"
    icon="add_photo_alternate"
    tone="primary"
    :action="route('knowledge.pages.steps.store', $page)"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('knowledge_pages.guide.add_step')">
    <label class="form-control" for="kp-step-image">
        <span class="label-text">{{ __('knowledge_pages.guide.image') }}</span>
        <input id="kp-step-image" type="file" name="image" accept="image/png,image/jpeg,image/webp" class="file-input file-input-bordered w-full" />
    </label>
    @error('image')<p class="text-sm text-error">{{ $message }}</p>@enderror
    <p class="text-xs text-muted">{{ __('knowledge_pages.guide.image_hint') }}</p>
    <x-textarea-field name="body" :label="__('knowledge_pages.guide.text')" rows="4" maxlength="5000" :value="old('body')" />
</x-modal>
