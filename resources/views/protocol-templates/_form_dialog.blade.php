{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Name, Zuordnung, Gültigkeit und Aktivierung einer Protokollvorlage (MVP-901). --}}
<x-modal
    :title="__('protocol.template.edit')"
    :eyebrow="$template->name . ' · v' . $template->version"
    icon="description"
    tone="primary"
    :action="route('protocol-templates.update', $template)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('protocol.action.update')">
    @include('protocol-templates._fields', ['template' => $template])
    <x-checkbox-field name="is_active" :label="__('protocol.template.active')" :checked="(bool) old('is_active', $template->is_active)" />
</x-modal>
