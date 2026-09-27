{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Arbeitsanweisung importieren (MVP-913); führt zur Vorschau, noch nichts wird angelegt. --}}
<x-modal :title="__('procedure.import.title')" :eyebrow="__('procedure.title.templates')" icon="upload_file" tone="primary"
         :action="route('procedures.import.preview')" method="POST" enctype="multipart/form-data" :submit-label="__('procedure.import.preview')">
    <p class="text-sm text-muted">{{ __('procedure.import.hint') }}</p>
    <div class="fieldset">
        <label for="import-file" class="fieldset-label">{{ __('procedure.import.file') }}</label>
        <input id="import-file" type="file" name="file" accept=".txt,.md,.pdf,.docx,.doc,.odt,.rtf" class="file-input file-input-bordered w-full">
    </div>
    <x-textarea-field name="text" :label="__('procedure.import.text')" rows="6" />
</x-modal>
