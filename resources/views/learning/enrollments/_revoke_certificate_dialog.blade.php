{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _revoke_certificate_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zertifikat widerrufen (Feature 149, MVP-1599): der Grund ist Pflicht und
  steht danach auf dem Ausdruck und in der Mitteilung an die Person.
  Variablen: $course, $enrollment, $certificate.
--}}
<x-modal
    :title="__('learning.action.revoke_certificate')"
    :eyebrow="$enrollment->learnerName()"
    icon="block"
    tone="error"
    :action="route('learning.courses.enrollments.certificate.revoke.store', [$course, $enrollment])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.revoke_certificate')">

    <x-form-group :legend="__('learning.field.certificate')" icon="workspace_premium" tone="error" cols="1">
        <x-detail-grid>
            <x-detail-grid.row :label="__('learning.field.certificate_number')" :value="$certificate->number" />
            <x-detail-grid.row :label="__('learning.field.issued_on')" :value="$certificate->issued_on->translatedFormat('d.m.Y')" />
        </x-detail-grid>
        <x-textarea-field name="reason" :label="__('learning.field.reason')" required minlength="5" maxlength="255" rows="2"
                          :hint="__('learning.help.revoke_certificate')" :value="old('reason')" />
    </x-form-group>
</x-modal>
