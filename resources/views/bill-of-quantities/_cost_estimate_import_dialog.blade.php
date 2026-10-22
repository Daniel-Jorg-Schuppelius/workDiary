{{--
  Created on   : Wed Oct 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _cost_estimate_import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Fremde Kostenermittlung (GAEB X51) übernehmen — so kommt das Budget in die
  Kostenverfolgung.
--}}
<x-modal
    :title="__('Kostenermittlung importieren')"
    :eyebrow="__('gaeb.title')"
    icon="upload"
    tone="primary"
    size="md"
    :action="route('bill-of-quantities.cost-estimate.import')"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Importieren')">
    <div class="fieldset">
        <label class="fieldset-label" for="cost-estimate-file">{{ __('Datei (GAEB X51)') }}</label>
        <input id="cost-estimate-file" type="file" name="file" accept=".x51,.X51,.xml,text/xml,application/xml" required
               class="file-input file-input-bordered w-full @error('file') file-input-error @enderror">
        @error('file')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
    </div>
    <x-project-select :label="__('Projekt (optional)')" :placeholder="__('ohne Projektbezug')" name="project"
        :projects="$projects" :selected="(string) old('project')" />
</x-modal>
