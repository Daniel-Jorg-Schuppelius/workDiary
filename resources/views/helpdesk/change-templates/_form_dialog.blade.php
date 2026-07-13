{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Modal (Feature 065, MVP-157): Change-Vorlage anlegen/bearbeiten.
  Bearbeiten erhöht die Version und zieht die Freigabe zurück; laufende
  Changes bleiben über ihren template_snapshot unberührt. Erwartet:
  $template, $isEdit.
--}}
@php
    /** @var \App\Models\ChangeTemplate $template */
    /** @var bool $isEdit */
    $action = $isEdit ? route('servicedesk.change-templates.update', $template) : route('servicedesk.change-templates.store');
@endphp

<x-modal
    :title="$isEdit ? __('Vorlage bearbeiten') : __('Neue Vorlage')"
    :eyebrow="__('Change-Vorlagen')"
    icon="library_books"
    tone="primary"
    size="md"
    :action="$action"
    :method="$isEdit ? 'PATCH' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-form-group :legend="__('Vorlage')" icon="library_books" tone="primary" cols="1">
        <x-input-field name="name" :label="__('Name')" required minlength="3" maxlength="150" :value="old('name', $template->name)" />
        <x-textarea-field name="implementation_plan" :label="__('Umsetzungsplan')" rows="3" maxlength="20000" :value="old('implementation_plan', $template->implementation_plan)" />
        <x-textarea-field name="test_plan" :label="__('Testplan')" rows="3" maxlength="20000" :value="old('test_plan', $template->test_plan)" />
        <x-textarea-field name="rollback_plan" :label="__('Rollback-Plan')" rows="3" maxlength="20000" :value="old('rollback_plan', $template->rollback_plan)" />
    </x-form-group>

    @if ($isEdit)
        <div class="alert alert-warning text-sm">
            {{ __('Speichern erhöht die Version und zieht die Freigabe zurück — bestehende Changes behalten ihren eingefrorenen Snapshot.') }}
        </div>
    @endif
</x-modal>
