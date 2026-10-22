{{--
  Created on   : Wed Oct 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _course_import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kurs aus einer WorkDiary-Exportdatei (JSON, „Kurs exportieren“) übernehmen;
  er entsteht immer als Entwurf. Keine Variablen.
--}}
<x-modal
    :title="__('learning.action.import_course')"
    :eyebrow="__('learning.section')"
    icon="upload_file"
    tone="primary"
    :action="route('learning.courses.import')"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.import')">
    <div class="fieldset">
        <label class="fieldset-label" for="course-import-file">{{ __('Exportdatei (JSON)') }}</label>
        <input type="file" id="course-import-file" name="file" accept=".json,application/json" required
               class="file-input file-input-bordered file-input-sm w-full @error('file') file-input-error @enderror">
        @error('file')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>
</x-modal>
