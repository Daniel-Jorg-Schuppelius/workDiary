{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _ask_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Frage an den Trainer (Feature 149, MVP-789). Variablen: $enrollment,
  $recipients (Verantwortliche/Trainer), $viaTicket (Helpdesk aktiv).
--}}
<x-modal
    :title="__('learning.title.ask_trainer')"
    :eyebrow="$enrollment->course?->title"
    icon="contact_support"
    tone="primary"
    :action="route('learning.my.ask.store', $enrollment)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.send_question')">

    <x-form-group :legend="__('learning.field.question')" icon="help" tone="primary" cols="1">
        <x-textarea-field name="question" :label="__('learning.field.question')" required minlength="5" maxlength="4000" rows="5" :value="old('question')" />
        <p class="text-xs text-muted">
            {{ __('learning.help.ask_recipients', ['names' => $recipients->pluck('name')->implode(', ')]) }}
            {{ $viaTicket ? __('learning.help.ask_via_ticket') : __('learning.help.ask_via_mail') }}
        </p>
    </x-form-group>
</x-modal>
