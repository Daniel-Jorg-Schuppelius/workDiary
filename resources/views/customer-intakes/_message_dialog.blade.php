{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _message_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Rückfrage an den Kunden (Dateien kundensichtbar) oder interne Notiz (Dateien intern). --}}
<x-modal
    :title="$kind === 'note' ? __('customer_intake.action.note') : __('customer_intake.action.ask')"
    :eyebrow="$intake->number"
    :icon="$kind === 'note' ? 'sticky_note_2' : 'contact_support'"
    :action="route('customer-intakes.message', $intake)"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="$kind === 'note' ? __('Speichern') : __('customer_intake.action.send_question')"
>
    <input type="hidden" name="kind" value="{{ $kind }}">
    <x-form-group :legend="$kind === 'note' ? __('customer_intake.message.note_legend') : __('customer_intake.message.question_legend')" :icon="$kind === 'note' ? 'lock' : 'forum'" cols="1">
        <x-textarea-field name="body" :label="__('customer_intake.field.message')" rows="5" maxlength="5000" required :value="old('body')"
                          :hint="$kind === 'note' ? __('customer_intake.message.note_hint') : __('customer_intake.message.question_hint')" />
        <x-upload-input :purpose="$intake->kind->uploadPurpose()" :show-errors="false" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
