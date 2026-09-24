{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Ausfüll-Dialog (Feature 032): dynamisch gerenderte Felder aus der
  AKTIVEN Vorlagen-Definition (in #entry-modal geladen).
  Variablen: $template (FormTemplate), $subjectKind (?string), $subjectId (?string)
--}}

<x-modal
    :title="__('form.action.fill') . ': ' . $template->name"
    :eyebrow="__('form.title.submissions')"
    icon="edit_note"
    tone="primary"
    size="lg"
    :action="route('form-submissions.store')"
    method="POST"
    :form-data="['data-entry-form' => '', 'enctype' => 'multipart/form-data', 'data-offline-sync' => 'form.submission']"
    :submit-label="__('form.action.submit')">

    <input type="hidden" name="form_template_id" value="{{ $template->sqid }}">
    @if ($subjectKind !== null)
        <input type="hidden" name="subject_kind" value="{{ $subjectKind }}">
        <input type="hidden" name="subject_id" value="{{ $subjectId }}">
    @endif

    @if ($template->description)
        <p class="mb-4 text-sm text-base-content/70">{{ $template->description }}</p>
    @endif

    @php
        $schema = \App\Services\Fields\FieldSchema::fromArray($template->fields);
        // Bedingungslogik (Rang 33): clientseitige Sichtbarkeit spiegelt
        // FieldSchema::isVisible. Nur Felder MIT Bedingung werden reaktiv
        // umgeschaltet; die Quell-Werte trackt der Wrapper generisch.
        $conditions = collect($schema->all())
            ->filter(fn ($f) => $f->hasCondition())
            ->mapWithKeys(fn ($f) => [$f->key => $f->visibleIf])
            ->all();
        $initialVals = collect($schema->all())
            ->mapWithKeys(function ($f) {
                $v = old("values.{$f->key}");
                if ($v === null) {
                    $v = $f->type === \App\Enums\Fields\FieldType::Boolean ? '0' : '';
                }

                return [$f->key => is_array($v) ? implode(',', $v) : (string) $v];
            })->all();
    @endphp
    <x-form-group :legend="$template->name" icon="edit_note" tone="primary" cols="2">
        <div class="contents"
             x-data="formFill"
             data-conditions="{{ json_encode($conditions) }}"
             data-initial="{{ json_encode($initialVals) }}"
             @input.capture="track($event)" @change.capture="track($event)">
        @foreach ($schema as $field)
            @if ($field->hasCondition())
                <div class="contents" x-show="visible('{{ $field->key }}')" x-cloak>
            @endif
            <x-field-input :field="$field" />
            @if ($field->hasCondition())
                </div>
            @endif
        @endforeach
        </div>
    </x-form-group>
</x-modal>
