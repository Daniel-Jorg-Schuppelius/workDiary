{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _ai_outline_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  KI-Gliederung (Feature 149, MVP-781): Thema und Zielgruppe → Abschnitte und
  Einheiten als Entwurf. Die KI schlägt vor, freigegeben wird von Hand.
  Variablen: $course, $topic.
--}}
<x-modal
    :title="__('learning.action.ai_outline')"
    :eyebrow="$course->title"
    icon="auto_awesome"
    tone="primary"
    :action="route('learning.courses.ai-outline', $course)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.ai_outline')">

    <x-form-group :legend="__('learning.field.topic')" icon="auto_awesome" tone="primary" cols="1">
        <x-textarea-field name="topic" :label="__('learning.field.topic')" required minlength="3" maxlength="2000" rows="4"
                          :hint="__('learning.help.ai_outline')" :value="old('topic', $topic)" />
        <x-input-field name="audience" :label="__('learning.field.audience')" maxlength="200" :value="old('audience')" />
    </x-form-group>
</x-modal>
