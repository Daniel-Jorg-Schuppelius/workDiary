{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Katalogfrage anlegen/bearbeiten (Feature 149, MVP-782) — dieselben Felder
  wie im Prüfungseditor. Variablen: $question (null beim Anlegen), $lines,
  $categories.
--}}
@php
    $editing = $question !== null;
@endphp
<x-modal
    :title="__($editing ? 'learning.field.editing_question' : 'learning.action.new_question')"
    :eyebrow="__('learning.title.questions')"
    icon="quiz"
    tone="primary"
    :action="$editing ? route('learning.questions.update', $question) : route('learning.questions.store')"
    :method="$editing ? 'PUT' : 'POST'"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__($editing ? 'learning.action.save' : 'learning.action.new_question')">

    @include('learning.courses._question_fields', ['question' => $question, 'lines' => $lines ?? '', 'categories' => $categories])
</x-modal>
