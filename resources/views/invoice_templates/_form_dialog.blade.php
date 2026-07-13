{{--
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : _form_dialog.blade.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Variablen: $template (InvoiceTemplate, ggf. ungespeichert) --}}
@php
    /** @var \App\Models\InvoiceTemplate $template */
    $isEdit = $template->exists;
    $action = $isEdit ? route('invoice-templates.update', $template) : route('invoice-templates.store');
@endphp

<x-modal
    :title="$isEdit ? __('Vorlage bearbeiten') : __('Neue Vorlage')"
    :eyebrow="__('Rechnungsvorlagen')"
    icon="receipt_long"
    tone="primary"
    size="md"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-form-group :legend="__('Allgemein')" icon="info" tone="primary" cols="2">
        <x-input-field name="name" :label="__('Name')" required maxlength="120" :value="old('name', $template->name)" />
        <x-input-field name="slug" :label="__('Slug')" required maxlength="64" pattern="[a-z0-9_-]+"
                       :hint="__('Nur a–z, 0–9, Bindestriche und Unterstriche.')"
                       :value="old('slug', $template->slug)" />
        <x-input-field name="accent_color" :label="__('Akzentfarbe')" maxlength="16"
                       :hint="__('Hex-Code, z. B. #2563eb.')"
                       :value="old('accent_color', $template->accent_color)" />
        <x-checkbox-field name="is_default" :label="__('Als Standard-Vorlage verwenden')"
                          :checked="(bool) old('is_default', $template->is_default)" />
    </x-form-group>

    <x-form-group :legend="__('Texte')" icon="notes" tone="primary" cols="1">
        <x-textarea-field name="header_text" :label="__('Kopftext')" rows="4" maxlength="2000"
                          :value="old('header_text', $template->header_text)" />
        <x-textarea-field name="footer_text" :label="__('Fußtext')" rows="4" maxlength="2000"
                          :value="old('footer_text', $template->footer_text)" />
    </x-form-group>
</x-modal>
