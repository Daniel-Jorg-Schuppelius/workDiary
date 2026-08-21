{{--
  Created on   : Thu Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Rundschreiben entwerfen (Feature 119, MVP-608). Der Empfängerkreis wird hier
  nur gefiltert — versendet wird erst nach der Vorschau mit Empfängerzahl.
--}}
<x-modal
    :title="__('circular.action.create')"
    icon="campaign"
    :action="route('circulars.store')"
    method="POST"
    :submit-label="__('circular.action.save_draft')"
>
    <x-input-field name="subject" type="text" maxlength="191" required
                   :label="__('circular.column.subject')"
                   :value="old('subject', '')" />

    <x-textarea-field name="body" rows="10" required
                      :label="__('circular.field.body')"
                      :value="old('body', '')"
                      :hint="__('circular.body_hint')" />

    <div class="grid gap-3 sm:grid-cols-3">
        <x-input-field name="search" type="text" maxlength="191"
                       :label="__('circular.filter.search')"
                       :value="old('search', '')" />
        <x-input-field name="city" type="text" maxlength="191"
                       :label="__('circular.filter.city')"
                       :value="old('city', '')" />
        <x-input-field name="zip_prefix" type="text" maxlength="10"
                       :label="__('circular.filter.zip_prefix')"
                       :value="old('zip_prefix', '')"
                       :hint="__('circular.filter.zip_hint')" />
    </div>

    <x-checkbox-field name="with_active_projects" :label="__('circular.filter.with_active_projects')"
                      :checked="(bool) old('with_active_projects')" />

    <x-checkbox-field name="is_mandatory" :label="__('circular.field.is_mandatory')"
                      :hint="__('circular.mandatory_hint')"
                      :checked="(bool) old('is_mandatory')" />

    <x-checkbox-field name="portal_notice" :label="__('circular.field.portal_notice')"
                      :hint="__('circular.portal_hint')"
                      :checked="(bool) old('portal_notice')" />
</x-modal>
