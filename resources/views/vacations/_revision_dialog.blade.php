{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _revision_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Urlaubsantrag zur Korrektur zurückgeben (MVP-1554). Variablen: $vacation
--}}
<x-modal
    :title="__('vacation.revision.title')"
    :eyebrow="__('Urlaubsverwaltung')"
    icon="undo"
    tone="info"
    :action="route('vacations.revision', $vacation)"
    method="PATCH"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('vacation.revision.action')">

    <div class="mb-4 rounded-box border border-base-300 bg-base-200/60 p-3 text-sm">
        <p class="font-semibold">{{ $vacation->user?->name }}</p>
        <p class="text-base-content/70">{{ $vacation->start_date->fdate() }} – {{ $vacation->end_date->fdate() }}</p>
        <p class="text-base-content/70">{{ $vacation->typeLabel() }}</p>
    </div>

    <input type="hidden" name="_dialog_url" value="{{ route('vacations.revision-form', $vacation) }}?dialog=1">

    <x-form-group :legend="__('vacation.revision.legend')" icon="undo" tone="info">
        <x-textarea-field name="revision_note" id="revision-note" rows="3" required minlength="5" maxlength="500"
                          :label="__('vacation.revision.note')"
                          :hint="__('vacation.revision.note_hint')"
                          :value="old('revision_note')" />
    </x-form-group>
</x-modal>
