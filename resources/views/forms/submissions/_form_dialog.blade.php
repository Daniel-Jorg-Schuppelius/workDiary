{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Ausfüll-Dialog (Feature 032): Felder aus der Vorlage bzw. dem eingefrorenen
  Stand des Entwurfs (in #entry-modal geladen). Lange Formulare (Feature 203):
  <x-field-form> gliedert in Abschnittskarten mit Fortschritt, ein langer
  Bogen öffnet als großer Dialog, jedes Feld wird beim Verlassen in den
  Entwurf gespeichert; „Absenden“ prüft alles samt Pflichtfeldern.
  Variablen: $template (FormTemplate), $subjectKind (?string), $subjectId (?string),
  $draft (?FormSubmission), $rebased (bool), $schema (FieldSchema), $values (FieldValues),
  $autosave (array — Konfiguration für <x-field-form>)
--}}
@php
    $long = \App\Services\Fields\FieldLayout::isLong($schema);
@endphp
<x-modal
    :title="__('form.action.fill') . ': ' . $template->name"
    :eyebrow="__('form.title.submissions')"
    icon="edit_note"
    tone="primary"
    :size="$long ? 'wide' : 'lg'"
    :class="$long ? 'wd-dialog--long' : null"
    :action="route('form-submissions.store')"
    method="POST"
    :form-data="['data-entry-form' => '', 'enctype' => 'multipart/form-data', 'data-offline-sync' => 'form.submission']"
    :submit-label="__('form.action.submit')">

    <input type="hidden" name="form_template_id" value="{{ $template->sqid }}">
    @if ($subjectKind !== null)
        <input type="hidden" name="subject_kind" value="{{ $subjectKind }}">
        <input type="hidden" name="subject_id" value="{{ $subjectId }}">
    @endif
    {{-- Entwurf (Feature 203): setzt der Browser nach dem ersten gespeicherten Feld; die Version führt die Warteschlange nach. --}}
    <input type="hidden" name="draft" value="{{ $draft?->sqid }}">
    <input type="hidden" name="draft_version" value="{{ $draft?->version }}">

    @if ($template->description)
        <p class="mb-2 text-sm text-base-content/70">{{ $template->description }}</p>
    @endif
    @if ($draft !== null)
        <p class="mb-2 text-sm text-muted">
            {{ __('form.draft.resumed', ['date' => $draft->updated_at?->fdatetime() ?? '—']) }}
        </p>
    @endif
    @if ($rebased)
        <div role="status" class="alert alert-info alert-soft mb-2 text-sm">{{ __('form.draft.rebased') }}</div>
    @endif

    <x-field-form :schema="$schema" :values="$values" :title="$template->name" notes talk :autosave="$autosave" />
</x-modal>
