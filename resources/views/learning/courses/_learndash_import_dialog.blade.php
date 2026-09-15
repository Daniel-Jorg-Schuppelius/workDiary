{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _learndash_import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  LearnDash-Import (Feature 149, MVP-792): Export-ZIP hochladen, Bericht
  im Anschluss. Keine Variablen.
--}}
<x-modal
    :title="__('learning.title.learndash_import')"
    :eyebrow="__('learning.section')"
    icon="upload_file"
    tone="primary"
    :action="route('learning.courses.import-learndash')"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.import')">

    <x-form-group :legend="__('learning.field.learndash_zip')" icon="archive" tone="primary" cols="1">
        <div>
            <label class="label" for="learndash-zip"><span class="label-text">{{ __('learning.field.learndash_zip') }}</span></label>
            <input type="file" id="learndash-zip" name="file" accept=".zip,application/zip" required class="file-input file-input-bordered file-input-sm w-full">
        </div>
        <x-checkbox-field name="dry_run" :label="__('learning.field.dry_run')" :hint="__('learning.help.learndash_dry_run')" />
        <p class="text-xs text-muted">{{ __('learning.help.learndash_import') }}</p>
    </x-form-group>
</x-modal>
